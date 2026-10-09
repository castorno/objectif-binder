<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\PullRateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * How often a card of a given rarity comes out of a booster of a given set:
 * `cardCount` cards for `boosterCount` boosters. Four commons in every
 * booster is 4 for 1; a rarity found once in eight boosters is 1 for 8.
 *
 * Two whole numbers rather than a decimal one: what was entered is kept
 * exactly, and 1 for 51 does not become 0.0196.
 *
 * The probability of pulling one *specific* card is derived at query time
 * from the number of cards of that rarity in the set (see
 * PullRateCalculator), never stored: it would go stale whenever a card of
 * that rarity is added to the set.
 */
#[ORM\Entity(repositoryClass: PullRateRepository::class)]
#[ORM\Table(name: 'pull_rate')]
#[ORM\UniqueConstraint(name: 'pull_rate_set_rarity_unique', columns: ['card_set_id', 'rarity_id'])]
class PullRate
{
    use UuidIdTrait;

    #[ORM\ManyToOne(targetEntity: CardSet::class)]
    #[ORM\JoinColumn(name: 'card_set_id', nullable: false)]
    private CardSet $cardSet;

    #[ORM\ManyToOne(targetEntity: Rarity::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Rarity $rarity;

    #[ORM\Column]
    #[Assert\Positive]
    private int $cardCount;

    #[ORM\Column]
    #[Assert\Positive]
    private int $boosterCount;

    /**
     * Where the figure comes from. Publishers rarely give their odds: most
     * figures are estimates someone made by opening many boosters, and are
     * shown as such.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    /** When the figure was last entered or changed. */
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(CardSet $cardSet, Rarity $rarity, int $cardCount, int $boosterCount, ?string $source = null, ?\DateTimeImmutable $updatedAt = null)
    {
        $this->id = Uuid::v7();
        $this->cardSet = $cardSet;
        $this->rarity = $rarity;
        $this->cardCount = $cardCount;
        $this->boosterCount = $boosterCount;
        $this->source = $source;
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function getCardSet(): CardSet
    {
        return $this->cardSet;
    }

    public function getRarity(): Rarity
    {
        return $this->rarity;
    }

    public function getCardCount(): int
    {
        return $this->cardCount;
    }

    public function getBoosterCount(): int
    {
        return $this->boosterCount;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return bool whether anything changed
     */
    public function update(int $cardCount, int $boosterCount, ?string $source, \DateTimeImmutable $now): bool
    {
        if ($this->cardCount === $cardCount && $this->boosterCount === $boosterCount && $this->source === $source) {
            return false;
        }

        $this->cardCount = $cardCount;
        $this->boosterCount = $boosterCount;
        $this->source = $source;
        $this->updatedAt = $now;

        return true;
    }
}
