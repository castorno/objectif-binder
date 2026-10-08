<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\Exception\InvalidRecordException;
use App\Import\ImportedCardFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImportedCardFactoryTest extends KernelTestCase
{
    use ImportFiles;

    private ImportedCardFactory $factory;

    protected function setUp(): void
    {
        $this->factory = static::getContainer()->get(ImportedCardFactory::class);
    }

    public function testBuildsACardFromACompleteRecord(): void
    {
        $card = $this->factory->fromArray($this->cardRecord('import-test', '012', '  Ember Wyrm '));

        self::assertSame('import-test', $card->gameSlug);
        self::assertSame('Import Test', $card->gameName);
        self::assertSame('Creatures', $card->gameIdentityLabel);
        self::assertSame('IT1', $card->setCode);
        self::assertSame('First Set', $card->setName);
        self::assertSame('2025-03-01', $card->setReleaseDate?->format('Y-m-d'));
        self::assertSame('012', $card->number);
        // Surrounding spaces are not part of a name.
        self::assertSame('Ember Wyrm', $card->name);
        self::assertSame('Common', $card->rarity);
        self::assertSame('it1-012', $card->externalId);
        self::assertSame(['type' => 'Creature'], $card->attributes);
        self::assertCount(1, $card->identities);
        self::assertSame('wyrm', $card->identities[0]->externalId);
        self::assertSame(1, $card->identities[0]->sortOrder);
    }

    public function testOnlyGameSetNumberAndNameAreRequired(): void
    {
        $card = $this->factory->fromArray([
            'game' => ['slug' => 'import-test', 'name' => 'Import Test'],
            'set' => ['code' => 'IT1', 'name' => 'First Set'],
            'number' => '1',
            'name' => 'Ember Wyrm',
        ]);

        self::assertNull($card->gameIdentityLabel);
        self::assertNull($card->setReleaseDate);
        self::assertNull($card->rarity);
        self::assertNull($card->externalId);
        self::assertNull($card->imageUrl);
        self::assertNull($card->largeImageUrl);
        self::assertSame([], $card->attributes);
        self::assertSame([], $card->identities);
    }

    public function testKeepsTheAddressesOfThePicturesOfACard(): void
    {
        $card = $this->factory->fromArray($this->cardRecord('import-test', '1', 'Ember Wyrm', [
            'imageUrl' => 'https://images.example.org/it1/1/low.webp',
            'largeImageUrl' => 'https://images.example.org/it1/1/high.webp',
        ]));

        self::assertSame('https://images.example.org/it1/1/low.webp', $card->imageUrl);
        self::assertSame('https://images.example.org/it1/1/high.webp', $card->largeImageUrl);
    }

    public function testAnIdentityListedTwiceIsKeptOnce(): void
    {
        $card = $this->factory->fromArray($this->cardRecord('import-test', '1', 'Ember Wyrm', [
            'identities' => [['externalId' => 'wyrm', 'name' => 'Wyrm'], ['externalId' => 'wyrm', 'name' => 'Wyrm']],
        ]));

        self::assertCount(1, $card->identities);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('invalidRecords')]
    public function testRefusesAnInvalidRecordAndNamesTheField(array $overrides, string $expectedField): void
    {
        try {
            $this->factory->fromArray($this->cardRecord('import-test', '1', 'Ember Wyrm', $overrides));
            self::fail('The record should have been refused.');
        } catch (InvalidRecordException $exception) {
            self::assertCount(1, $exception->messages, implode(' | ', $exception->messages));
            self::assertStringStartsWith($expectedField.': ', $exception->messages[0]);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidRecords(): iterable
    {
        $game = ['slug' => 'import-test', 'name' => 'Import Test'];
        $set = ['code' => 'IT1', 'name' => 'First Set'];

        yield 'missing name' => [['name' => null], 'name'];
        yield 'blank name' => [['name' => '   '], 'name'];
        yield 'name too long' => [['name' => str_repeat('a', 201)], 'name'];
        yield 'number that is not text' => [['number' => 12], 'number'];
        yield 'number too long' => [['number' => str_repeat('1', 21)], 'number'];
        yield 'game that is not an object' => [['game' => 'import-test'], 'game'];
        yield 'game slug with capitals' => [['game' => ['slug' => 'Import Test'] + $game], 'game.slug'];
        yield 'missing game name' => [['game' => ['slug' => 'import-test']], 'game.name'];
        yield 'missing set code' => [['set' => ['name' => 'First Set']], 'set.code'];
        yield 'release date in another format' => [['set' => $set + ['releaseDate' => '01/03/2025']], 'set.releaseDate'];
        yield 'release date that does not exist' => [['set' => $set + ['releaseDate' => '2025-02-30']], 'set.releaseDate'];
        yield 'empty rarity' => [['rarity' => ''], 'rarity'];
        yield 'attributes that are not an object' => [['attributes' => 'fire'], 'attributes'];
        yield 'identities that are not a list' => [['identities' => ['externalId' => 'wyrm', 'name' => 'Wyrm']], 'identities'];
        yield 'identity without external id' => [['identities' => [['name' => 'Wyrm']]], 'identities.0.externalId'];
        yield 'identity order that is not a number' => [['identities' => [['externalId' => 'wyrm', 'name' => 'Wyrm', 'sortOrder' => '4']]], 'identities.0.sortOrder'];
        // The address ends up in an image tag: only plain https ones get there.
        yield 'picture served without https' => [['imageUrl' => 'http://images.example.org/1.webp'], 'imageUrl'];
        yield 'picture that is a script' => [['imageUrl' => 'javascript:alert(1)'], 'imageUrl'];
        yield 'picture embedded in the address' => [['largeImageUrl' => 'data:image/png;base64,AAAA'], 'largeImageUrl'];
        yield 'picture address that is not text' => [['imageUrl' => ['https://images.example.org/1.webp']], 'imageUrl'];
        yield 'unknown field' => [['colour' => 'red'], 'colour'];
    }

    public function testReportsEveryProblemOfARecordAtOnce(): void
    {
        try {
            $this->factory->fromArray(['game' => ['slug' => 'import-test', 'name' => 'Import Test']]);
            self::fail('The record should have been refused.');
        } catch (InvalidRecordException $exception) {
            self::assertCount(3, $exception->messages);
        }
    }
}
