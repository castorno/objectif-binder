<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Entity\Card;
use App\Entity\CardPrice;
use App\Repository\CardPriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Lock\LockFactory;

/**
 * The estimated price of a card, asked from a source only when needed.
 *
 * A source serves prices one card at a time: pricing a whole catalog would
 * take one request per card, again and again as prices move. Instead a
 * price is asked for when someone looks at the card, kept, and only asked
 * again once it is older than MAX_AGE. The source gets at most one request
 * per card per month, and none for the cards nobody looks at.
 */
final class CardPriceService
{
    /** A price older than this is asked for again. */
    public const string MAX_AGE = '30 days';

    /**
     * @param iterable<CardPriceProvider> $providers
     */
    public function __construct(
        #[AutowireIterator('app.card_price_provider')]
        private readonly iterable $providers,
        private readonly CardPriceRepository $cardPriceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LockFactory $lockFactory,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return CardPrice|null null when no source knows the card, or when its
     *                        price was never obtained and cannot be right now
     */
    public function priceOf(Card $card): ?CardPrice
    {
        $price = $this->cardPriceRepository->findOneBy(['card' => $card]);
        $now = $this->clock->now();

        if (null !== $price && $price->getFetchedAt() > $now->modify('-'.self::MAX_AGE)) {
            return $price;
        }

        $provider = $this->providerFor($card);
        if (null === $provider) {
            return $price;
        }

        // Two visitors opening the same card at once: one asks the source,
        // the other makes do with what is already known.
        $lock = $this->lockFactory->createLock('card-price-'.$card->getId());
        if (!$lock->acquire()) {
            return $price;
        }

        try {
            $quote = $provider->fetch($card);
        } catch (PriceUnavailableException $exception) {
            // An old price beats none; it will be asked for again next time.
            $this->logger->warning('Card price could not be refreshed.', ['card' => (string) $card->getId(), 'exception' => $exception]);

            return $price;
        } finally {
            $lock->release();
        }

        if (null === $price) {
            $price = new CardPrice($card, $quote, $now);
            $this->entityManager->persist($price);
        } else {
            $price->update($quote, $now);
        }
        $this->entityManager->flush();

        return $price;
    }

    private function providerFor(Card $card): ?CardPriceProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($card)) {
                return $provider;
            }
        }

        return null;
    }
}
