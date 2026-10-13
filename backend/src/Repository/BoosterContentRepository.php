<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CardSet;
use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * What the boosters of sets hold, counted for the odds of a booster (see
 * BoosterOddsService). A booster of a set holds the cards of the set and
 * of its sub-sets: everything here is counted by main set.
 *
 * Written in SQL: these are counts over several sets at once, grouped by a
 * set or its parent, which the ORM's query language does not express well.
 * Each method is one query whatever the number of sets.
 */
final class BoosterContentRepository
{
    /**
     * A booster bought in a language only holds the cards printed in it. The
     * same rule as the search of cards (see CardRepository): unknown
     * languages rule nothing out.
     */
    private const string LANGUAGE_CONDITION = 'AND (c.languages IS NULL OR jsonb_exists(CAST(c.languages AS jsonb), :language))';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * How many cards each rarity has in the boosters of each set, and how
     * many of them the user does not own. Cards without rarity are left out:
     * no pull rate can be given to them.
     *
     * @param list<CardSet> $mainSets
     * @param string|null   $language only the cards printed in that language: see LANGUAGE_CONDITION
     *
     * @return array<string, array<string, array{total: int, missing: int}>> by main set id, then by rarity id
     */
    public function countCardsByRarity(array $mainSets, User $user, ?string $language = null): array
    {
        $counts = [];
        foreach ($this->fetch(
            <<<'SQL'
                SELECT COALESCE(s.parent_id, s.id) AS main_set_id, c.rarity_id,
                       COUNT(*) AS total,
                       COUNT(*) FILTER (WHERE NOT EXISTS (
                           SELECT 1 FROM owned_card o WHERE o.card_id = c.id AND o.user_id = :user
                       )) AS missing
                FROM card c
                JOIN card_set s ON s.id = c.card_set_id
                WHERE COALESCE(s.parent_id, s.id) IN (:sets) AND c.rarity_id IS NOT NULL {language}
                GROUP BY 1, 2
                SQL,
            $mainSets,
            $user,
            $language,
        ) as $row) {
            $counts[(string) $row['main_set_id']][(string) $row['rarity_id']] = ['total' => (int) $row['total'], 'missing' => (int) $row['missing']];
        }

        return $counts;
    }

    /**
     * How many cards of each identity each rarity has in the boosters of
     * each set. A card showing two identities counts for both.
     *
     * @param list<CardSet> $mainSets
     * @param string|null   $language only the cards printed in that language: see LANGUAGE_CONDITION
     *
     * @return array<string, array<string, array{name: string, cards: array<string, int>}>> by main set id, then by identity id;
     *                                                                                      cards: by rarity id
     */
    public function countCardsByIdentityAndRarity(array $mainSets, ?string $language = null): array
    {
        $counts = [];
        foreach ($this->fetch(
            <<<'SQL'
                SELECT COALESCE(s.parent_id, s.id) AS main_set_id, i.id AS identity_id, i.name, c.rarity_id, COUNT(*) AS cards
                FROM card c
                JOIN card_set s ON s.id = c.card_set_id
                JOIN card_identity_link l ON l.card_id = c.id
                JOIN card_identity i ON i.id = l.card_identity_id
                WHERE COALESCE(s.parent_id, s.id) IN (:sets) AND c.rarity_id IS NOT NULL {language}
                GROUP BY 1, 2, 3, 4
                SQL,
            $mainSets,
            language: $language,
        ) as $row) {
            $setId = (string) $row['main_set_id'];
            $identityId = (string) $row['identity_id'];

            $counts[$setId][$identityId] ??= ['name' => (string) $row['name'], 'cards' => []];
            $counts[$setId][$identityId]['cards'][(string) $row['rarity_id']] = (int) $row['cards'];
        }

        return $counts;
    }

    /**
     * The identities the user owns at least one card of, in any set.
     *
     * @return array<string, true> by identity id
     */
    public function findOwnedIdentityIds(User $user): array
    {
        $ids = $this->connection->executeQuery(
            <<<'SQL'
                SELECT DISTINCT l.card_identity_id
                FROM owned_card o
                JOIN card_identity_link l ON l.card_id = o.card_id
                WHERE o.user_id = :user
                SQL,
            ['user' => $user->getId()->toRfc4122()],
        )->fetchFirstColumn();

        return array_fill_keys(array_map(strval(...), $ids), true);
    }

    /**
     * @param string        $sql      with "{language}" where the condition on the language of a card goes
     * @param list<CardSet> $mainSets
     *
     * @return list<array<string, mixed>>
     */
    private function fetch(string $sql, array $mainSets, ?User $user = null, ?string $language = null): array
    {
        if ([] === $mainSets) {
            return [];
        }

        $sql = str_replace('{language}', null === $language ? '' : self::LANGUAGE_CONDITION, $sql);

        $parameters = ['sets' => array_map(static fn (CardSet $set): string => $set->getId()->toRfc4122(), $mainSets)];
        if (null !== $user) {
            $parameters['user'] = $user->getId()->toRfc4122();
        }
        if (null !== $language) {
            $parameters['language'] = $language;
        }

        return $this->connection->executeQuery($sql, $parameters, ['sets' => ArrayParameterType::STRING])->fetchAllAssociative();
    }
}
