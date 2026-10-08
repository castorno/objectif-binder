<?php

declare(strict_types=1);

namespace App\Tests\Import\Tcgdex;

use App\Import\ImportedCardFactory;
use App\Import\Source\Tcgdex\TcgdexCardMapper;
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
            'game' => ['slug' => 'pokemon', 'name' => 'Pokémon', 'identityLabel' => 'Pokédex'],
            'set' => ['code' => 'ef1', 'name' => 'Premières Braises', 'releaseDate' => '2025-03-01'],
            'number' => '1',
            'name' => 'Braisewyrm V',
            'externalId' => 'ef1-1',
            'rarity' => 'Rare',
            'attributes' => ['category' => 'Pokémon', 'types' => ['Feu'], 'hp' => 190, 'stage' => 'Base'],
            // Named after the species, not after this card.
            'identities' => [['externalId' => 'pokedex-7', 'name' => 'Braisewyrm', 'sortOrder' => 7]],
        ], $record);
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
