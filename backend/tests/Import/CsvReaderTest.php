<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\Exception\UnreadableSourceException;
use App\Import\Reader\CsvReader;
use App\Import\Reader\SourceRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvReaderTest extends TestCase
{
    use ImportFiles;

    private const string HEADER = 'game_slug,game_name,set_code,set_name,number,name';

    private CsvReader $reader;

    protected function setUp(): void
    {
        $this->reader = new CsvReader();
    }

    protected function tearDown(): void
    {
        $this->removeImportFiles();
    }

    public function testSupportsCsvFilesOnly(): void
    {
        self::assertTrue($this->reader->supports('/tmp/Cards.CSV'));
        self::assertFalse($this->reader->supports('/tmp/cards.jsonl'));
    }

    public function testRebuildsTheNestedRecordFromFlatColumns(): void
    {
        $records = $this->records(<<<'CSV'
            game_slug,game_name,game_identity_label,set_code,set_name,set_release_date,number,name,rarity,external_id,identity_ids,identity_names,identity_sort_orders,attribute:element,attribute:type
            emberfall,Emberfall,Créatures,EF1,Premières Braises,2025-03-01,001,Wyrm de braise,Rare,ef1-001,wyrm|renard,Wyrm | Renard,1|2,Feu,Créature
            CSV);

        self::assertCount(1, $records);
        // The header is line 1.
        self::assertSame(2, $records[0]->position);
        self::assertSame([
            'game' => ['slug' => 'emberfall', 'name' => 'Emberfall', 'identityLabel' => 'Créatures'],
            'set' => ['code' => 'EF1', 'name' => 'Premières Braises', 'releaseDate' => '2025-03-01'],
            // Text, not a number: the leading zeros are part of it.
            'number' => '001',
            'name' => 'Wyrm de braise',
            'rarity' => 'Rare',
            'externalId' => 'ef1-001',
            'attributes' => ['element' => 'Feu', 'type' => 'Créature'],
            'identities' => [
                ['externalId' => 'wyrm', 'name' => 'Wyrm', 'sortOrder' => 1],
                ['externalId' => 'renard', 'name' => 'Renard', 'sortOrder' => 2],
            ],
        ], $records[0]->data);
    }

    public function testAnEmptyOptionalCellIsLeftOutOfTheRecord(): void
    {
        $records = $this->records(<<<'CSV'
            game_slug,game_name,set_code,set_name,number,name,rarity,identity_ids,identity_names,attribute:element
            emberfall,Emberfall,EF1,Premières Braises,004,Source d'énergie,,,,
            CSV);

        self::assertSame([
            'game' => ['slug' => 'emberfall', 'name' => 'Emberfall'],
            'set' => ['code' => 'EF1', 'name' => 'Premières Braises'],
            'number' => '004',
            'name' => "Source d'énergie",
        ], $records[0]->data);
    }

    /**
     * Left out, a required value would be reported as an unknown problem;
     * passed on empty, the validation names the field.
     */
    public function testAnEmptyRequiredCellIsPassedOnForTheValidationToReport(): void
    {
        $records = $this->records(self::HEADER."\nemberfall,Emberfall,EF1,Premières Braises,001,\n");

        self::assertSame('', $records[0]->data['name'] ?? null);
    }

    public function testReadsSemicolonSeparatedFilesAndQuotedCells(): void
    {
        $records = $this->records(
            "\xEF\xBB\xBF".str_replace(',', ';', self::HEADER)."\r\n"
            .'emberfall;Emberfall;EF1;"Braises; cendres";001;"Wyrm ""le grand"""'."\r\n",
        );

        self::assertSame('Braises; cendres', $records[0]->data['set']['name'] ?? null);
        self::assertSame('Wyrm "le grand"', $records[0]->data['name'] ?? null);
    }

    public function testSkipsBlankLinesAndReportsALineWithTheWrongNumberOfCells(): void
    {
        $records = $this->records(self::HEADER."\n\nemberfall,Emberfall,EF1\nemberfall,Emberfall,EF1,Braises,002,Wyrm de givre\n");

        self::assertCount(2, $records);
        self::assertSame(3, $records[0]->position);
        self::assertSame('The line has 3 cells where the header names 6 columns.', $records[0]->error);
        self::assertSame(4, $records[1]->position);
        self::assertSame('Wyrm de givre', $records[1]->data['name'] ?? null);
    }

    public function testReportsIdentityColumnsThatDoNotMatch(): void
    {
        $records = $this->records(self::HEADER.",identity_ids,identity_names\nemberfall,Emberfall,EF1,Braises,001,Wyrm,wyrm|renard,Wyrm\n");

        self::assertNull($records[0]->data);
        self::assertStringContainsString('same number of values', (string) $records[0]->error);
    }

    public function testLeavesAnIdentityOrderThatIsNotANumberForTheValidation(): void
    {
        $records = $this->records(self::HEADER.",identity_ids,identity_names,identity_sort_orders\nemberfall,Emberfall,EF1,Braises,001,Wyrm,wyrm,Wyrm,first\n");

        self::assertSame('first', $records[0]->data['identities'][0]['sortOrder'] ?? null);
    }

    /**
     * What a spreadsheet saves by default on some systems: accented letters
     * would be stored as garbage.
     */
    public function testReportsALineThatIsNotUtf8(): void
    {
        $records = $this->records(self::HEADER."\nemberfall,Emberfall,EF1,".mb_convert_encoding('Premières', 'ISO-8859-1', 'UTF-8').",001,Wyrm\n");

        self::assertSame('The line is not encoded in UTF-8.', $records[0]->error);
    }

    #[DataProvider('unusableHeaders')]
    public function testRefusesAFileWhoseHeaderIsWrongBeforeReadingAnyLine(string $content, string $expectedMessage): void
    {
        $path = $this->importFile($content, 'csv');

        $this->expectException(UnreadableSourceException::class);
        $this->expectExceptionMessage($expectedMessage);

        // Not iterated: the failure must not wait for the first record.
        $this->reader->read($path);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unusableHeaders(): iterable
    {
        yield 'empty file' => ['', 'The file is empty'];
        yield 'unknown column' => [self::HEADER.",colour\n", 'Unknown column: "colour".'];
        yield 'attribute without a name' => [self::HEADER.",attribute:\n", 'Unknown column: "attribute:".'];
        yield 'missing required columns' => ["game_slug,game_name,set_code,number\n", 'Missing column: "set_name", "name".'];
        yield 'column named twice' => [self::HEADER.",name\n", 'Column named more than once: "name".'];
    }

    public function testRefusesAMissingFile(): void
    {
        $this->expectException(UnreadableSourceException::class);

        $this->reader->read('/nowhere/cards.csv');
    }

    /**
     * @return list<SourceRecord>
     */
    private function records(string $content): array
    {
        // Heredocs above are indented for reading; a real file is not.
        $content = preg_replace('/^ {12}/m', '', $content);

        return iterator_to_array($this->reader->read($this->importFile((string) $content, 'csv')), false);
    }
}
