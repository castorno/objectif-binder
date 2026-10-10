<?php

declare(strict_types=1);

namespace App\Tests\Import\Tcgdex;

use App\Import\ImportedCardFactory;
use App\Import\Source\Tcgdex\TcgdexCardMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TcgdexCardMapperTest extends KernelTestCase
{
    use TcgdexResponses;

    private const array SET = ['id' => 'ef1', 'name' => 'Premières Braises', 'releaseDate' => '2025-03-01'];

    private TcgdexCardMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new TcgdexCardMapper();
    }

    public function testMapsACreatureCard(): void
    {
        $record = $this->mapper->toRecord(self::SET, $this->cardsOfSet()[0], [7 => 'Braisewyrm']);

        self::assertSame([
            'game' => ['slug' => 'pokemon', 'name' => 'Pokémon', 'identityLabel' => 'Pokédex', 'identityGroupLabel' => 'Génération'],
            'set' => ['code' => 'ef1', 'name' => 'Premières Braises', 'releaseDate' => '2025-03-01'],
            'number' => '1',
            'name' => 'Braisewyrm V',
            'externalId' => 'ef1-1',
            'rarity' => 'Rare',
            'rarityOrder' => 30,
            'attributes' => ['category' => 'Pokémon', 'types' => ['Feu'], 'hp' => 190, 'stage' => 'Base'],
            'finishes' => ['holo'],
            // Named after the species, not after this card.
            'identities' => [['externalId' => 'pokedex-7', 'name' => 'Braisewyrm', 'sortOrder' => 7, 'group' => ['name' => 'Génération 1', 'order' => 1]]],
        ], $record);
    }

    /**
     * TCGdex names rarities without ranking them: the order, from the most
     * common to the hardest to find, is told along with each card.
     */
    public function testRanksTheRaritiesItKnows(): void
    {
        $rank = fn (string $rarity): mixed => $this->mapper->toRecord(self::SET, ['rarity' => $rarity] + $this->cardsOfSet()[0], [])['rarityOrder'] ?? null;

        self::assertSame(10, $rank('Commune'));
        self::assertSame(20, $rank('Peu Commune'));
        self::assertLessThan($rank('Illustration spéciale rare'), $rank('Double rare'));
        // Close names, far apart: the second is a secret rare.
        self::assertLessThan($rank('Magnifique rare'), $rank('Magnifique'));
        // Not degrees of rarity: after all those that are.
        self::assertLessThan($rank('Promo'), $rank('RGB Rare'));
        // A rarity TCGdex would add later: no rank is made up.
        self::assertNull($rank('Rareté inédite'));
    }

    /**
     * Every rarity TCGdex lists in French has a rank. When this fails, TCGdex
     * added one: give it a place in TcgdexCardMapper::RARITIES_IN_ORDER.
     */
    public function testKnowsEveryRarityTcgdexListed(): void
    {
        // As GET /v2/fr/rarities answered on 2026-10-10.
        $listed = ['Chromatique ultra rare', 'Collection Classique', 'Commune', 'Couronne', 'Deux Chromatiques', 'Deux Diamants', 'Deux Étoiles', 'Double rare', 'Dresseur Full Art', 'Futuristic Rare', 'HIGH-TECH rare', 'Holo Rare', 'Holo Rare V', 'Holo Rare VMAX', 'Holo Rare VSTAR', 'Hyper rare', 'Illustration rare', 'Illustration spéciale rare', 'LÉGENDE', 'Magnifique', 'Magnifique rare', 'Mega Attack Rare', 'Méga Hyper Rare', 'Peu Commune', 'Pikachu Rare', 'Promo', 'Quatre Diamants', 'RGB Rare', 'Radieux Rare', 'Rare', 'Rare Holo', 'Rare Holo LV.X', 'Rare Noir Blanc', 'Rare Prime', 'Sans Rareté', 'Shiny rare', 'Shiny rare V', 'Shiny rare VMAX', 'Trois Diamants', 'Trois Étoiles', 'Ultra Rare', 'Un Chromatique', 'Un Diamant', 'Une Étoile'];

        $ranks = [];
        foreach ($listed as $rarity) {
            $record = $this->mapper->toRecord(self::SET, ['rarity' => $rarity] + $this->cardsOfSet()[0], []);
            self::assertArrayHasKey('rarityOrder', $record, $rarity);
            $ranks[] = $record['rarityOrder'];
        }

        // No two rarities share a rank.
        self::assertCount(\count($listed), array_unique($ranks));
    }

    /**
     * What decides later whether a price for a shiny version of the card
     * means anything.
     */
    public function testTellsWhichFinishesACardWasPrintedWith(): void
    {
        [$holoOnly, $plainAndReverse, $unknown] = $this->cardsOfSet();

        self::assertSame(['holo'], $this->mapper->toRecord(self::SET, $holoOnly, [])['finishes']);
        self::assertSame(['normal', 'reverse'], $this->mapper->toRecord(self::SET, $plainAndReverse, [])['finishes']);
        // TCGdex does not say: nothing is made up.
        self::assertArrayNotHasKey('finishes', $this->mapper->toRecord(self::SET, $unknown, []));
    }

    /**
     * The pictures are artwork TCGdex does not license: a record only
     * points to them when that was asked for.
     */
    public function testLeavesThePicturesOutUnlessAskedForThem(): void
    {
        $card = $this->cardsOfSet()[0];

        $without = $this->mapper->toRecord(self::SET, $card, []);
        self::assertArrayNotHasKey('imageUrl', $without);
        self::assertArrayNotHasKey('largeImageUrl', $without);

        $with = $this->mapper->toRecord(self::SET, $card, [], withImages: true);
        self::assertSame('https://assets.example.org/fr/ef1/1/low.webp', $with['imageUrl']);
        self::assertSame('https://assets.example.org/fr/ef1/1/high.webp', $with['largeImageUrl']);

        // A card TCGdex has no picture of.
        self::assertArrayNotHasKey('imageUrl', $this->mapper->toRecord(self::SET, $this->cardsOfSet()[2], [], withImages: true));
    }

    /**
     * Species are numbered in the order they appeared: the number alone
     * says which generation a species is from.
     */
    #[DataProvider('generations')]
    public function testPutsASpeciesInTheGenerationItsNumberBelongsTo(int $speciesNumber, ?string $expectedGroup): void
    {
        $record = $this->mapper->toRecord(self::SET, ['dexId' => [$speciesNumber]] + $this->cardsOfSet()[0], []);

        self::assertSame($expectedGroup, $record['identities'][0]['group']['name'] ?? null);
    }

    /**
     * @return iterable<string, array{int, ?string}>
     */
    public static function generations(): iterable
    {
        yield 'first species' => [1, 'Génération 1'];
        yield 'last of the first generation' => [151, 'Génération 1'];
        yield 'first of the second generation' => [152, 'Génération 2'];
        yield 'last of the fourth generation' => [493, 'Génération 4'];
        yield 'first of the ninth generation' => [906, 'Génération 9'];
        yield 'last species known' => [1025, 'Génération 9'];
        // Newer than this code: better no generation than a wrong one.
        yield 'species from a generation not known yet' => [1026, null];
    }

    public function testACardShowingTwoSpeciesGetsBothIdentities(): void
    {
        $record = $this->mapper->toRecord(self::SET, $this->cardsOfSet()[1], [7 => 'Braisewyrm', 12 => 'Givrenard']);

        self::assertSame(['pokedex-7', 'pokedex-12'], array_column($record['identities'], 'externalId'));
        self::assertSame(['Braisewyrm', 'Givrenard'], array_column($record['identities'], 'name'));
    }

    public function testACardThatIsNotACreatureHasNoIdentityAndNoEmptyAttribute(): void
    {
        $record = $this->mapper->toRecord(self::SET, $this->cardsOfSet()[2], []);

        self::assertArrayNotHasKey('identities', $record);
        self::assertSame(['category' => 'Énergie'], $record['attributes']);
    }

    public function testASpeciesWithoutAKnownNameIsNamedByItsNumber(): void
    {
        $record = $this->mapper->toRecord(self::SET, $this->cardsOfSet()[0], []);

        self::assertSame('N° 7', $record['identities'][0]['name']);
    }

    public function testNamesASpeciesAfterTheShortestNameOfTheCardsShowingOnlyIt(): void
    {
        $names = $this->mapper->speciesNames([
            ['name' => 'Braisewyrm V', 'dexId' => [7]],
            ['name' => 'Braisewyrm', 'dexId' => [7]],
            ['name' => 'Braisewyrm de Lia', 'dexId' => [7]],
            // Shorter than "Givrenard", but it shows two species: it names neither.
            ['name' => 'Duo', 'dexId' => [7, 12]],
            ['name' => 'Givrenard', 'dexId' => [12]],
            ['name' => 'Sans numéro', 'dexId' => null],
        ]);

        self::assertSame([7 => 'Braisewyrm', 12 => 'Givrenard'], $names);
    }

    public function testTheNameOfASpeciesDoesNotDependOnTheOrderOfTheCards(): void
    {
        $cards = [['name' => 'Wyrm B', 'dexId' => [7]], ['name' => 'Wyrm A', 'dexId' => [7]]];

        self::assertSame($this->mapper->speciesNames($cards), $this->mapper->speciesNames(array_reverse($cards)));
        self::assertSame([7 => 'Wyrm A'], $this->mapper->speciesNames($cards));
    }

    /**
     * The contract between this source and the import: whatever the mapper
     * produces, the import accepts.
     */
    public function testEveryRecordItProducesIsAValidImportRecord(): void
    {
        $factory = static::getContainer()->get(ImportedCardFactory::class);

        foreach ($this->cardsOfSet() as $card) {
            $imported = $factory->fromArray($this->mapper->toRecord(self::SET, $card, [7 => 'Braisewyrm', 12 => 'Givrenard'], withImages: true));

            self::assertSame('ef1', $imported->setCode);
        }
    }
}
