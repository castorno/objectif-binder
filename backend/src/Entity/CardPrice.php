<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Pricing\PriceQuote;
use App\Repository\CardPriceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The last price a source gave for a card: an estimate for a card that is
 * not graded, all languages and conditions together.
 *
 * Asked for when someone opens the card, and kept: the source is only asked
 * again once this is too old (see CardPriceService). A card the source has
 * no price for still gets a row, with empty amounts, so that it is not
 * asked for at every visit.
 *
 * Amounts are in cents: see PriceQuote.
 */
#[ORM\Entity(repositoryClass: CardPriceRepository::class)]
#[ORM\Table(name: 'card_price')]
class CardPrice
{
    use UuidIdTrait;

    #[ORM\OneToOne(targetEntity: Card::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Card $card;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $marketplace = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(nullable: true)]
    private ?int $trendCents = null;

    #[ORM\Column(nullable: true)]
    private ?int $lowCents = null;

    #[ORM\Column(name: 'average_30_days_cents', nullable: true)]
    private ?int $average30DaysCents = null;

    #[ORM\Column(nullable: true)]
    private ?int $holoTrendCents = null;

    #[ORM\Column(nullable: true)]
    private ?int $holoLowCents = null;

    #[ORM\Column(name: 'holo_average_30_days_cents', nullable: true)]
    private ?int $holoAverage30DaysCents = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sourceUpdatedAt = null;

    /**
     * The page of the marketplace these figures are about. A source matches
     * its cards to marketplace products, and can get it wrong: the link lets
     * a person see which product the price really is the price of.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productUrl = null;

    /**
     * When the source was last asked, whatever it answered.
     */
    #[ORM\Column]
    private \DateTimeImmutable $fetchedAt;

    public function __construct(Card $card, ?PriceQuote $quote, \DateTimeImmutable $fetchedAt)
    {
        $this->id = Uuid::v7();
        $this->card = $card;
        $this->fetchedAt = $fetchedAt;
        $this->update($quote, $fetchedAt);
    }

    /**
     * @param PriceQuote|null $quote null when the source has no price for the card
     */
    public function update(?PriceQuote $quote, \DateTimeImmutable $fetchedAt): void
    {
        $this->fetchedAt = $fetchedAt;
        $this->marketplace = $quote?->marketplace;
        $this->currency = $quote?->currency;
        $this->trendCents = $quote?->trendCents;
        $this->lowCents = $quote?->lowCents;
        $this->average30DaysCents = $quote?->average30DaysCents;
        $this->holoTrendCents = $quote?->holoTrendCents;
        $this->holoLowCents = $quote?->holoLowCents;
        $this->holoAverage30DaysCents = $quote?->holoAverage30DaysCents;
        $this->sourceUpdatedAt = $quote?->sourceUpdatedAt;
        $this->productUrl = $quote?->productUrl;
    }

    /**
     * Whether the source gave any amount at all.
     *
     * @param bool $withShinyAmounts false to leave out those of the shiny version
     */
    public function hasAmounts(bool $withShinyAmounts = true): bool
    {
        if (null !== ($this->trendCents ?? $this->lowCents ?? $this->average30DaysCents)) {
            return true;
        }

        return $withShinyAmounts && null !== ($this->holoTrendCents ?? $this->holoLowCents ?? $this->holoAverage30DaysCents);
    }

    public function getCard(): Card
    {
        return $this->card;
    }

    public function getMarketplace(): ?string
    {
        return $this->marketplace;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getTrendCents(): ?int
    {
        return $this->trendCents;
    }

    public function getLowCents(): ?int
    {
        return $this->lowCents;
    }

    public function getAverage30DaysCents(): ?int
    {
        return $this->average30DaysCents;
    }

    public function getHoloTrendCents(): ?int
    {
        return $this->holoTrendCents;
    }

    public function getHoloLowCents(): ?int
    {
        return $this->holoLowCents;
    }

    public function getHoloAverage30DaysCents(): ?int
    {
        return $this->holoAverage30DaysCents;
    }

    public function getSourceUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->sourceUpdatedAt;
    }

    public function getProductUrl(): ?string
    {
        return $this->productUrl;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }
}
