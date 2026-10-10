<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\Rarity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Writes imported cards to the catalog, creating what does not exist yet and
 * updating what does. Everything is found by its natural key, the one the
 * database keeps unique, so importing the same card twice changes nothing:
 * an interrupted import is resumed by running it again.
 *
 * Cards are written in batches: import() only prepares the changes, flush()
 * sends them and frees the memory they used.
 */
final class CardImporter implements ResetInterface
{
    /*
     * What the current batch has already looked up or created, by natural
     * key. A new game or set is not in the database until the batch is
     * flushed: without this, the next card of the same set would create it
     * a second time.
     */

    /** @var array<string, Game> */
    private array $games = [];
    /** @var array<string, CardSet> */
    private array $sets = [];
    /** @var array<string, Rarity> */
    private array $rarities = [];
    /** @var array<string, CardIdentity> */
    private array $identities = [];
    /** @var array<string, Card> */
    private array $cards = [];
    /** @var array<string, int> by game slug */
    private array $lastRaritySortOrders = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function import(ImportedCard $imported): ImportOutcome
    {
        $game = $this->game($imported);
        $set = $this->set($game, $imported);
        // A set may give all its cards one rarity, whatever the source says (see CardSet::$forcedRarity).
        $rarity = $set->getForcedRarity()
            ?? (null === $imported->rarity ? null : $this->rarity($game, $imported->rarity, $imported->rarityOrder));
        $identities = array_map(fn (ImportedIdentity $identity): CardIdentity => $this->identity($game, $identity), $imported->identities);

        $key = $game->getSlug().'|'.$set->getCode().'|'.$imported->number;
        $card = $this->cards[$key]
            ?? $this->entityManager->getRepository(Card::class)->findOneBy(['cardSet' => $set, 'numberInSet' => $imported->number]);

        if (null === $card) {
            $card = new Card($set, $imported->name, $imported->number);
            $card->setRarity($rarity);
            $card->setExternalId($imported->externalId);
            $card->setImageUrl($imported->imageUrl);
            $card->setLargeImageUrl($imported->largeImageUrl);
            $card->setAttributes($imported->attributes);
            $card->setFinishes($imported->finishes);
            foreach ($identities as $identity) {
                $card->addIdentity($identity);
            }

            $this->entityManager->persist($card);
            $this->cards[$key] = $card;

            return ImportOutcome::Created;
        }

        return $this->update($card, $imported, $rarity, $identities) ? ImportOutcome::Updated : ImportOutcome::Unchanged;
    }

    /**
     * Sends the batch to the database, then forgets every entity read or
     * created so far: memory use stays flat however long the import is.
     */
    public function flush(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->reset();
    }

    public function reset(): void
    {
        $this->games = $this->sets = $this->rarities = $this->identities = $this->cards = [];
        $this->lastRaritySortOrders = [];
    }

    /**
     * @param list<CardIdentity> $identities
     */
    private function update(Card $card, ImportedCard $imported, ?Rarity $rarity, array $identities): bool
    {
        $changed = false;

        if ($card->getName() !== $imported->name) {
            $card->setName($imported->name);
            $changed = true;
        }
        if ($card->getRarity() !== $rarity) {
            $card->setRarity($rarity);
            $changed = true;
        }
        if ($card->getExternalId() !== $imported->externalId) {
            $card->setExternalId($imported->externalId);
            $changed = true;
        }
        if ($card->getImageUrl() !== $imported->imageUrl) {
            $card->setImageUrl($imported->imageUrl);
            $changed = true;
        }
        if ($card->getLargeImageUrl() !== $imported->largeImageUrl) {
            $card->setLargeImageUrl($imported->largeImageUrl);
            $changed = true;
        }
        // Loose comparison on purpose: the same attributes in another order
        // are the same attributes.
        if ($card->getAttributes() != $imported->attributes) {
            $card->setAttributes($imported->attributes);
            $changed = true;
        }
        // Optional in a record: leaving them out keeps the finishes as they are.
        if (null !== $imported->finishes && $card->getFinishes() !== $imported->finishes) {
            $card->setFinishes($imported->finishes);
            $changed = true;
        }

        foreach ($card->getIdentities()->toArray() as $current) {
            if (!\in_array($current, $identities, true)) {
                $card->removeIdentity($current);
                $changed = true;
            }
        }
        foreach ($identities as $identity) {
            if (!$card->getIdentities()->contains($identity)) {
                $card->addIdentity($identity);
                $changed = true;
            }
        }

        return $changed;
    }

    private function game(ImportedCard $imported): Game
    {
        $game = $this->games[$imported->gameSlug]
            ?? $this->entityManager->getRepository(Game::class)->findOneBy(['slug' => $imported->gameSlug]);

        if (null === $game) {
            $game = new Game($imported->gameName, $imported->gameSlug);
            $this->entityManager->persist($game);
            $this->games[$imported->gameSlug] = $game;
            // Nothing to look up for a game that did not exist a moment ago.
            $this->lastRaritySortOrders[$imported->gameSlug] = -1;
        }

        if ($game->getName() !== $imported->gameName) {
            $game->setName($imported->gameName);
        }
        // Optional in a record: leaving it out keeps the label as it is.
        if (null !== $imported->gameIdentityLabel && $game->getIdentityLabel() !== $imported->gameIdentityLabel) {
            $game->setIdentityLabel($imported->gameIdentityLabel);
        }
        if (null !== $imported->gameIdentityGroupLabel && $game->getIdentityGroupLabel() !== $imported->gameIdentityGroupLabel) {
            $game->setIdentityGroupLabel($imported->gameIdentityGroupLabel);
        }

        return $game;
    }

    private function set(Game $game, ImportedCard $imported): CardSet
    {
        $key = $game->getSlug().'|'.$imported->setCode;
        $set = $this->sets[$key]
            ?? $this->entityManager->getRepository(CardSet::class)->findOneBy(['game' => $game, 'code' => $imported->setCode]);

        if (null === $set) {
            $set = new CardSet($game, $imported->setName, $imported->setCode);
            $this->entityManager->persist($set);
            $this->sets[$key] = $set;
        }

        if ($set->getName() !== $imported->setName) {
            $set->setName($imported->setName);
        }
        if (null !== $imported->setReleaseDate && $set->getReleaseDate() != $imported->setReleaseDate) {
            $set->setReleaseDate($imported->setReleaseDate);
        }

        return $set;
    }

    private function rarity(Game $game, string $name, ?int $sortOrder): Rarity
    {
        $key = $game->getSlug().'|'.$name;
        $rarity = $this->rarities[$key]
            ?? $this->entityManager->getRepository(Rarity::class)->findOneBy(['game' => $game, 'name' => $name]);

        if (null === $rarity) {
            // A source that does not rank its rarities gets them in order of
            // appearance, after those the game already has.
            $rarity = new Rarity($game, $name, $sortOrder ?? $this->nextRaritySortOrder($game));
            $this->entityManager->persist($rarity);
            $this->rarities[$key] = $rarity;
        } elseif (null !== $sortOrder && $rarity->getSortOrder() !== $sortOrder) {
            // Optional in a record: leaving it out keeps the rank as it is.
            $rarity->setSortOrder($sortOrder);
        }

        if (null !== $sortOrder) {
            // The next rarity without a rank still goes after all the others.
            $this->lastRaritySortOrders[$game->getSlug()] = max($this->lastRaritySortOrder($game), $sortOrder);
        }

        return $rarity;
    }

    private function nextRaritySortOrder(Game $game): int
    {
        return $this->lastRaritySortOrders[$game->getSlug()] = $this->lastRaritySortOrder($game) + 1;
    }

    /**
     * The highest rank among the rarities of the game, those of the current
     * batch included; -1 when it has none.
     */
    private function lastRaritySortOrder(Game $game): int
    {
        return $this->lastRaritySortOrders[$game->getSlug()] ??= (int) ($this->entityManager->createQueryBuilder()
            ->select('MAX(r.sortOrder)')
            ->from(Rarity::class, 'r')
            ->where('r.game = :game')
            ->setParameter('game', $game)
            ->getQuery()
            ->getSingleScalarResult() ?? -1);
    }

    private function identity(Game $game, ImportedIdentity $imported): CardIdentity
    {
        $key = $game->getSlug().'|'.$imported->externalId;
        $identity = $this->identities[$key]
            ?? $this->entityManager->getRepository(CardIdentity::class)->findOneBy(['game' => $game, 'externalId' => $imported->externalId]);

        if (null === $identity) {
            $identity = new CardIdentity($game, $imported->name, $imported->externalId);
            $this->entityManager->persist($identity);
            $this->identities[$key] = $identity;
        }

        if ($identity->getName() !== $imported->name) {
            $identity->setName($imported->name);
        }
        // Optional in a record: leaving it out keeps the number as it is.
        if (null !== $imported->sortOrder && $identity->getSortOrder() !== $imported->sortOrder) {
            $identity->setSortOrder($imported->sortOrder);
        }
        // Same for the group: a record that does not name one changes nothing.
        if (null !== $imported->groupName && ($identity->getGroupName() !== $imported->groupName || $identity->getGroupOrder() !== $imported->groupOrder)) {
            $identity->setGroup($imported->groupName, $imported->groupOrder);
        }

        return $identity;
    }
}
