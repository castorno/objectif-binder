<?php

declare(strict_types=1);

namespace App\Tests\Import\Tcgdex;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Answers shaped like those of TCGdex, for tests that never touch the
 * network. The cards are invented.
 */
trait TcgdexResponses
{
    /**
     * @param int $listed how many cards the set lists in the requested language
     */
    private function setResponse(string $id = 'ef1', int $listed = 3, string $serie = 'ef'): MockResponse
    {
        $cards = [];
        for ($number = 1; $number <= $listed; ++$number) {
            $cards[] = ['id' => $id.'-'.$number, 'localId' => (string) $number, 'name' => 'Carte '.$number];
        }

        return new MockResponse(json_encode([
            'id' => $id,
            'name' => 'Premières Braises',
            'releaseDate' => '2025-03-01',
            'serie' => ['id' => $serie, 'name' => 'Série'],
            // The size of the set worldwide: larger than what one language got.
            'cardCount' => ['total' => $listed + 20, 'official' => $listed + 20],
            'cards' => $cards,
        ], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string, mixed>> $cards
     */
    private function cardsResponse(array $cards): MockResponse
    {
        return new MockResponse(json_encode(['data' => ['cards' => $cards]], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cardsOfSet(string $setId = 'ef1'): array
    {
        return [
            ['id' => $setId.'-1', 'localId' => '1', 'name' => 'Braisewyrm V', 'rarity' => 'Rare', 'category' => 'Pokémon', 'dexId' => [7], 'types' => ['Feu'], 'hp' => 190, 'stage' => 'Base', 'image' => 'https://assets.example.org/fr/'.$setId.'/1', 'set' => ['id' => $setId]],
            ['id' => $setId.'-2', 'localId' => '2', 'name' => 'Braisewyrm et Givrenard', 'rarity' => 'Ultra Rare', 'category' => 'Pokémon', 'dexId' => [7, 12], 'types' => ['Feu', 'Eau'], 'hp' => 250, 'stage' => 'Base', 'set' => ['id' => $setId]],
            ['id' => $setId.'-3', 'localId' => '3', 'name' => 'Énergie Braise', 'rarity' => 'Commune', 'category' => 'Énergie', 'dexId' => null, 'types' => null, 'hp' => null, 'stage' => null, 'set' => ['id' => $setId]],
        ];
    }

    /**
     * The pictures TCGdex has in English for the cards of a set: here for
     * card 2, which has none in French, but not for card 3.
     */
    private function fallbackPicturesResponse(string $setId = 'ef1'): MockResponse
    {
        return $this->cardsResponse([
            ['id' => $setId.'-1', 'image' => 'https://assets.example.org/en/'.$setId.'/1', 'set' => ['id' => $setId]],
            ['id' => $setId.'-2', 'image' => 'https://assets.example.org/en/'.$setId.'/2', 'set' => ['id' => $setId]],
            ['id' => $setId.'-3', 'image' => null, 'set' => ['id' => $setId]],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function creatureNames(): array
    {
        return [
            ['name' => 'Braisewyrm V', 'dexId' => [7]],
            ['name' => 'Braisewyrm', 'dexId' => [7]],
            ['name' => 'Givrenard', 'dexId' => [12]],
            ['name' => 'Braisewyrm et Givrenard', 'dexId' => [7, 12]],
        ];
    }
}
