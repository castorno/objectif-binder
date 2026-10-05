<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\PullRateRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Odds of pulling a card of a given rarity from a booster of a given set,
 * expressed as "1 chance in `oddsOneIn`".
 *
 * The probability of pulling one *specific* card is derived at query time as
 * `oddsOneIn * count(Card WHERE set = this.cardSet AND rarity = this.rarity)`,
 * never stored: it would go stale whenever a card of that rarity is added to the set.
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
    private int $oddsOneIn;

    public function __construct(CardSet $cardSet, Rarity $rarity, int $oddsOneIn)
    {
        $this->id = Uuid::v7();
        $this->cardSet = $cardSet;
        $this->rarity = $rarity;
        $this->oddsOneIn = $oddsOneIn;
    }

    public function getCardSet(): CardSet
    {
        return $this->cardSet;
    }

    public function getRarity(): Rarity
    {
        return $this->rarity;
    }

    public function getOddsOneIn(): int
    {
        return $this->oddsOneIn;
    }

    public function setOddsOneIn(int $oddsOneIn): static
    {
        $this->oddsOneIn = $oddsOneIn;

        return $this;
    }
}
