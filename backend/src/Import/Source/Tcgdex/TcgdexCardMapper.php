<?php

declare(strict_types=1);

namespace App\Import\Source\Tcgdex;

/**
 * Turns what TCGdex says about a card into a record of the import format
 * (see docs/import.md). Everything specific to this source and to its game
 * stops here: past this point a card is a card like any other.
 */
final class TcgdexCardMapper
{
    public const string GAME_SLUG = 'pokemon';

    private const string GAME_NAME = 'Pokémon';

    /** What this game calls its index of species. */
    private const string IDENTITY_LABEL = 'Pokédex';

    /** What this game calls the eras its species appeared in. */
    private const string IDENTITY_GROUP_LABEL = 'Génération';

    /**
     * The number of the last species of each generation, by generation.
     * TCGdex does not say which generation a species is from, but the
     * numbering does: species are numbered in the order they appeared.
     * A species numbered past the last one known here gets no generation.
     */
    private const array LAST_SPECIES_OF_GENERATION = [1 => 151, 2 => 251, 3 => 386, 4 => 493, 5 => 649, 6 => 721, 7 => 809, 8 => 905, 9 => 1025];

    /**
     * The rarities TCGdex names, in French as the catalog is, from the most
     * common to the hardest to find. TCGdex does not rank them: this order
     * is ours. Names for the same thing in different eras sit together, and
     * what is not a degree of rarity (a classic collection, a promo, no
     * rarity at all) comes after the degrees. The last ones are those of the
     * mobile game, which is not imported.
     *
     * A rarity missing from this list gets no rank, and the import puts it
     * after all the others.
     */
    private const array RARITIES_IN_ORDER = [
        'Commune', 'Peu Commune', 'Rare',
        'Holo Rare', 'Rare Holo EX', 'Rare Holo LV.X', 'Rare Holo Étoile', 'Rare Prime', 'LÉGENDE',
        'Holo Rare V', 'Holo Rare VSTAR', 'Holo Rare VMAX', 'Radieux Rare', 'Magnifique', 'HIGH-TECH rare',
        'Double rare', 'Ultra Rare', 'Dresseur Full Art', 'Illustration rare', 'Illustration spéciale rare',
        'Shiny rare', 'Shiny rare V', 'Shiny rare VMAX', 'Chromatique ultra rare',
        // "Magnifique rare" is what TCGdex calls a secret rare in French: far from "Magnifique".
        'Magnifique rare', 'Hyper rare', 'Rare Noir Blanc', 'Mega Attack Rare', 'Méga Hyper Rare',
        'Pikachu Rare', 'Futuristic Rare', 'RGB Rare',
        'Collection Classique', 'Promo', 'Sans Rareté',
        'Un Diamant', 'Deux Diamants', 'Trois Diamants', 'Quatre Diamants', 'Une Étoile', 'Deux Étoiles', 'Trois Étoiles',
        'Un Chromatique', 'Deux Chromatiques', 'Couronne',
    ];

    /**
     * What TCGdex calls, in the fallback language, the things the catalog
     * names in its own: a card only found in English still gets the rarity,
     * types, stage and category of the others, not a second set of them.
     * Paired from the cards TCGdex has in both languages. A word missing
     * here is kept as it is.
     */
    private const array RARITIES_FROM_ENGLISH = [
        'Common' => 'Commune', 'Uncommon' => 'Peu Commune', 'None' => 'Sans Rareté',
        'Rare PRIME' => 'Rare Prime', 'LEGEND' => 'LÉGENDE', 'Radiant Rare' => 'Radieux Rare', 'Amazing Rare' => 'Magnifique',
        'ACE SPEC Rare' => 'HIGH-TECH rare', 'Full Art Trainer' => 'Dresseur Full Art', 'Special illustration rare' => 'Illustration spéciale rare',
        'Shiny Ultra Rare' => 'Chromatique ultra rare', 'Secret Rare' => 'Magnifique rare', 'Black White Rare' => 'Rare Noir Blanc',
        'Mega Hyper Rare' => 'Méga Hyper Rare', 'Classic Collection' => 'Collection Classique',
    ];

    /**
     * Two names TCGdex gives the same rarity in different eras: the catalog
     * keeps one.
     */
    private const array SAME_RARITIES = ['Rare Holo' => 'Holo Rare'];

    /**
     * The rarities too broad to tell a holo from an "ex" or a secret rare:
     * TCGdex gives them to the older sets whatever the card. See refinedRarity().
     */
    private const array BROAD_RARITIES = ['Commune', 'Peu Commune', 'Rare', 'Holo Rare'];

    private const array CATEGORIES_FROM_ENGLISH = ['Pokemon' => 'Pokémon', 'Trainer' => 'Dresseur', 'Energy' => 'Énergie'];

    private const array TYPES_FROM_ENGLISH = [
        'Grass' => 'Plante', 'Fire' => 'Feu', 'Water' => 'Eau', 'Lightning' => 'Électrique', 'Psychic' => 'Psy', 'Fighting' => 'Combat',
        'Darkness' => 'Obscurité', 'Metal' => 'Métal', 'Fairy' => 'Fée', 'Colorless' => 'Incolore',
    ];

    private const array STAGES_FROM_ENGLISH = [
        'Basic' => 'Base', 'Stage1' => 'Niveau 1', 'Stage2' => 'Niveau 2', 'Baby' => 'Bébé', 'LEVEL-UP' => 'Niveau Sup',
        'BREAK' => 'TURBO', 'MEGA' => 'MÉGA', 'RESTORED' => 'Restauré',
    ];

    /**
     * Where the artwork of a species is served, by species number. Not by
     * TCGdex, which only has pictures of cards: by the PokeAPI project.
     */
    private const string SPECIES_ARTWORK_URL = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/%d.png';

    /** Ranks are this far apart, to leave room between two of them. */
    private const int RARITY_RANK_STEP = 10;

    /**
     * @param array<string, mixed> $set          as TcgdexClient::fetchSet() returns it
     * @param array<string, mixed> $card         one of TcgdexClient::fetchCards()
     * @param array<int, string>   $speciesNames by species number: see speciesNames()
     * @param bool                 $withImages   also keep where the pictures of the card and of its species are served
     * @param list<string>         $languages    the languages TCGdex has the card in; without the catalog's
     *                                           one, the card is read as written in the fallback language
     *
     * @return array<string, mixed>
     */
    public function toRecord(array $set, array $card, array $speciesNames, bool $withImages = false, array $languages = []): array
    {
        if ([] !== $languages && !\in_array(TcgdexClient::LANGUAGE, $languages, true)) {
            $card = $this->inCatalogWords($card);
        }

        $record = [
            'game' => [
                'slug' => self::GAME_SLUG,
                'name' => self::GAME_NAME,
                'identityLabel' => self::IDENTITY_LABEL,
                'identityGroupLabel' => self::IDENTITY_GROUP_LABEL,
            ],
            'set' => ['code' => $set['id'] ?? null, 'name' => $set['name'] ?? null],
            'number' => $card['localId'] ?? null,
            'name' => $card['name'] ?? null,
            'externalId' => $card['id'] ?? null,
        ];

        if (\is_string($set['releaseDate'] ?? null) && '' !== $set['releaseDate']) {
            $record['set']['releaseDate'] = $set['releaseDate'];
        }
        if (\is_string($card['rarity'] ?? null) && '' !== trim($card['rarity'])) {
            $record['rarity'] = $this->refinedRarity(self::SAME_RARITIES[trim($card['rarity'])] ?? $card['rarity'], $set, $card);

            $rank = array_search(trim($record['rarity']), self::RARITIES_IN_ORDER, true);
            if (false !== $rank) {
                $record['rarityOrder'] = ($rank + 1) * self::RARITY_RANK_STEP;
            }
        }

        // The logo of the set is a mark of the publisher: under the same
        // option as the pictures of the cards. TCGdex gives the start of its
        // address, the format ends it.
        if ($withImages && \is_string($set['logo'] ?? null) && str_starts_with($set['logo'], 'https://')) {
            $record['set']['logoUrl'] = $set['logo'].'.webp';
        }

        // TCGdex gives the start of the address; the size and format end it.
        // The pictures are artwork owned by the game's publisher, not part
        // of what TCGdex licenses: left out unless explicitly asked for.
        if ($withImages && \is_string($card['image'] ?? null) && str_starts_with($card['image'], 'https://')) {
            $record['imageUrl'] = $card['image'].'/low.webp';
            $record['largeImageUrl'] = $card['image'].'/high.webp';
        }

        $attributes = array_filter(
            [
                'category' => $card['category'] ?? null,
                'types' => $card['types'] ?? null,
                'hp' => $card['hp'] ?? null,
                'stage' => $card['stage'] ?? null,
            ],
            static fn (mixed $value): bool => null !== $value && [] !== $value && '' !== $value,
        );
        if ([] !== $attributes) {
            $record['attributes'] = $attributes;
        }

        // Which finishes the card was printed with. A first edition or a
        // stamped promo is another print run, not another finish.
        if (\is_array($card['variants'] ?? null)) {
            $record['finishes'] = array_values(array_filter(
                ['normal', 'holo', 'reverse'],
                static fn (string $finish): bool => true === ($card['variants'][$finish] ?? null),
            ));
        }

        if ([] !== $languages) {
            $record['languages'] = $languages;
        }

        // The species a card shows: one for most creatures, several for a
        // card showing more than one, none for the other kinds of cards.
        foreach ($this->speciesNumbers($card) as $number) {
            $identity = [
                'externalId' => 'pokedex-'.$number,
                'name' => $speciesNames[$number] ?? 'N° '.$number,
                'sortOrder' => $number,
            ];

            $generation = $this->generationOf($number);
            if (null !== $generation) {
                $identity['group'] = ['name' => 'Génération '.$generation, 'order' => $generation];
            }

            // Artwork owned by the game's publisher, like the pictures of
            // the cards: under the same option.
            if ($withImages) {
                $identity['imageUrl'] = sprintf(self::SPECIES_ARTWORK_URL, $number);
            }

            $record['identities'][] = $identity;
        }

        return $record;
    }

    /**
     * Names each species from the cards showing it. TCGdex gives a card the
     * number of its species, not the species' name, and the card's own name
     * often says more ("X V", "X de Y"). The shortest name among the cards
     * showing only that species is nearly always the plain one.
     *
     * A rule of thumb, not a fact: a species that never had a plain card
     * gets an imperfect name.
     *
     * @param list<array<string, mixed>> $creatureCards TcgdexClient::fetchCreatureNames()
     *
     * @return array<int, string> by species number
     */
    public function speciesNames(array $creatureCards): array
    {
        $names = [];

        foreach ($creatureCards as $card) {
            $numbers = $this->speciesNumbers($card);
            $name = \is_string($card['name'] ?? null) ? trim($card['name']) : '';

            // A card showing two species names neither of them.
            if (1 !== \count($numbers) || '' === $name) {
                continue;
            }

            $current = $names[$numbers[0]] ?? null;
            if (null === $current || $this->isPlainerThan($name, $current)) {
                $names[$numbers[0]] = $name;
            }
        }

        ksort($names);

        return $names;
    }

    /**
     * A finer rarity than TCGdex gives the older sets, where one "Rare" holds
     * plain rares, holos, "ex" and secret rares alike, which come out of a
     * booster at very different rates. Only from what the card says for
     * sure: a gold star in its name, a number past the set's official count
     * (an energy aside),
     * an "ex" after its name. A holo cannot be told this way (TCGdex lists
     * finishes some holos were never printed with), so it keeps its rarity.
     *
     * @param array<string, mixed> $set
     * @param array<string, mixed> $card
     */
    private function refinedRarity(string $rarity, array $set, array $card): string
    {
        if (!\in_array(trim($rarity), self::BROAD_RARITIES, true)) {
            return $rarity;
        }

        $name = \is_string($card['name'] ?? null) ? $card['name'] : '';
        $number = \is_string($card['localId'] ?? null) ? $card['localId'] : '';
        $officialCount = $set['cardCount']['official'] ?? null;

        if (str_contains($name, '☆')) {
            return 'Rare Holo Étoile';
        }
        // Some sets number their basic energies past the official count too.
        $isEnergy = 'Énergie' === ($card['category'] ?? null);
        if (!$isEnergy && \is_int($officialCount) && $officialCount > 0 && ctype_digit($number) && (int) $number > $officialCount) {
            return 'Magnifique rare';
        }
        if (1 === preg_match('/ (ex|EX)( |$)/u', $name)) {
            return 'Rare Holo EX';
        }

        return $rarity;
    }

    /**
     * A card of the fallback language, with what can be said in the words of
     * the catalog's language. Its name stays as it is: that card has no other.
     *
     * @param array<string, mixed> $card
     *
     * @return array<string, mixed>
     */
    private function inCatalogWords(array $card): array
    {
        foreach (['rarity' => self::RARITIES_FROM_ENGLISH, 'category' => self::CATEGORIES_FROM_ENGLISH, 'stage' => self::STAGES_FROM_ENGLISH] as $field => $words) {
            if (\is_string($card[$field] ?? null)) {
                $card[$field] = $words[trim($card[$field])] ?? $card[$field];
            }
        }

        if (\is_array($card['types'] ?? null)) {
            $card['types'] = array_map(
                static fn (mixed $type): mixed => \is_string($type) ? self::TYPES_FROM_ENGLISH[$type] ?? $type : $type,
                $card['types'],
            );
        }

        return $card;
    }

    private function generationOf(int $speciesNumber): ?int
    {
        foreach (self::LAST_SPECIES_OF_GENERATION as $generation => $lastSpecies) {
            if ($speciesNumber <= $lastSpecies) {
                return $generation;
            }
        }

        return null;
    }

    private function isPlainerThan(string $name, string $other): bool
    {
        // Same length: alphabetical order, so the result does not depend on
        // the order the cards came in.
        return [mb_strlen($name), $name] < [mb_strlen($other), $other];
    }

    /**
     * @param array<string, mixed> $card
     *
     * @return list<int>
     */
    private function speciesNumbers(array $card): array
    {
        $numbers = \is_array($card['dexId'] ?? null) ? $card['dexId'] : [];

        return array_values(array_unique(array_filter($numbers, static fn (mixed $number): bool => \is_int($number) && $number > 0)));
    }
}
