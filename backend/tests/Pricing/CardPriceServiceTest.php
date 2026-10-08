<?php

declare(strict_types=1);

namespace App\Tests\Pricing;

use App\Entity\Card;
use App\Entity\CardPrice;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Pricing\CardPriceProvider;
use App\Pricing\CardPriceService;
use App\Pricing\PriceQuote;
use App\Pricing\PriceUnavailableException;
use App\Repository\CardPriceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;

final class CardPriceServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Card $card;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        $this->clock = new MockClock('2026-10-08 12:00:00');

        $game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $set = new CardSet($game, 'Set', 'SET-'.uniqid());
        $this->card = new Card($set, 'Ember Wyrm', '001');
        array_map($this->em->persist(...), [$game, $set, $this->card]);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->close();

        parent::tearDown();
    }

    public function testAsksTheSourceTheFirstTimeAndKeepsTheAnswer(): void
    {
        $provider = $this->provider([$this->quote(trend: 120)]);

        $price = $this->service($provider)->priceOf($this->card);

        self::assertSame(120, $price?->getTrendCents());
        self::assertSame('Cardmarket', $price?->getMarketplace());
        self::assertSame(1, $provider->calls);
        self::assertSame(1, $this->em->getRepository(CardPrice::class)->count(['card' => $this->card]));
    }

    /**
     * The point of keeping prices: a card looked at every day costs the
     * source one request a month.
     */
    public function testDoesNotAskAgainWhileThePriceIsLessThanThirtyDaysOld(): void
    {
        $provider = $this->provider([$this->quote(trend: 120), $this->quote(trend: 999)]);
        $service = $this->service($provider);
        $service->priceOf($this->card);

        $this->clock->modify('+29 days');
        $price = $service->priceOf($this->card);

        self::assertSame(120, $price?->getTrendCents());
        self::assertSame(1, $provider->calls);
    }

    public function testAsksAgainOnceThePriceIsMoreThanThirtyDaysOld(): void
    {
        $provider = $this->provider([$this->quote(trend: 120), $this->quote(trend: 150)]);
        $service = $this->service($provider);
        $service->priceOf($this->card);

        $this->clock->modify('+31 days');
        $price = $service->priceOf($this->card);

        self::assertSame(150, $price?->getTrendCents());
        self::assertSame(2, $provider->calls);
        // Updated in place: still one price per card.
        self::assertSame(1, $this->em->getRepository(CardPrice::class)->count(['card' => $this->card]));
        self::assertEquals($this->clock->now(), $price?->getFetchedAt());
    }

    /**
     * "No price" is an answer too: it is kept, or the source would be asked
     * at every visit of a card it knows nothing about.
     */
    public function testRemembersThatTheSourceHasNoPriceForACard(): void
    {
        $provider = $this->provider([null, $this->quote(trend: 120)]);
        $service = $this->service($provider);

        $price = $service->priceOf($this->card);
        self::assertNotNull($price);
        self::assertFalse($price->hasAmounts());

        $service->priceOf($this->card);
        self::assertSame(1, $provider->calls);
    }

    public function testKeepsTheOldPriceWhenTheSourceCannotBeAsked(): void
    {
        $provider = $this->provider([$this->quote(trend: 120), new PriceUnavailableException('Down.'), $this->quote(trend: 150)]);
        $service = $this->service($provider);
        $service->priceOf($this->card);

        $this->clock->modify('+31 days');
        self::assertSame(120, $service->priceOf($this->card)?->getTrendCents());

        // Not recorded as fresh: the next visit tries again.
        self::assertSame(150, $service->priceOf($this->card)?->getTrendCents());
        self::assertSame(3, $provider->calls);
    }

    public function testHasNoPriceWhenTheSourceFailsOnTheFirstTry(): void
    {
        self::assertNull($this->service($this->provider([new PriceUnavailableException('Down.')]))->priceOf($this->card));
    }

    public function testHasNoPriceForACardNoSourceKnows(): void
    {
        $provider = $this->provider([$this->quote(trend: 120)], supports: false);

        self::assertNull($this->service($provider)->priceOf($this->card));
        self::assertSame(0, $provider->calls);
    }

    /**
     * Two requests for the same card at once: the second does not send a
     * second request to the source.
     */
    public function testDoesNotAskTheSourceWhileAnotherRequestIsAskingForTheSameCard(): void
    {
        $provider = $this->provider([$this->quote(trend: 120)]);
        $lock = static::getContainer()->get(LockFactory::class)->createLock('card-price-'.$this->card->getId());
        self::assertTrue($lock->acquire());

        try {
            self::assertNull($this->service($provider)->priceOf($this->card));
            self::assertSame(0, $provider->calls);
        } finally {
            $lock->release();
        }
    }

    private function quote(int $trend): PriceQuote
    {
        return new PriceQuote('Cardmarket', 'EUR', $trend, 50, 110, null, null, null, new \DateTimeImmutable('2026-10-08 09:00:00'));
    }

    /**
     * A source answering what it is given, in order.
     *
     * @param list<PriceQuote|PriceUnavailableException|null> $answers
     */
    private function provider(array $answers, bool $supports = true): CardPriceProvider
    {
        return new class($answers, $supports) implements CardPriceProvider {
            public int $calls = 0;

            /**
             * @param list<PriceQuote|PriceUnavailableException|null> $answers
             */
            public function __construct(private array $answers, private readonly bool $supports)
            {
            }

            public function supports(Card $card): bool
            {
                return $this->supports;
            }

            public function fetch(Card $card): ?PriceQuote
            {
                ++$this->calls;
                $answer = array_shift($this->answers);

                return $answer instanceof \Throwable ? throw $answer : $answer;
            }
        };
    }

    private function service(CardPriceProvider $provider): CardPriceService
    {
        $container = static::getContainer();

        return new CardPriceService(
            [$provider],
            $container->get(CardPriceRepository::class),
            $this->em,
            $container->get(LockFactory::class),
            $this->clock,
            new NullLogger(),
        );
    }
}
