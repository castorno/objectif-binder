<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Import\Exception\UnreadableSourceException;
use App\Import\Reader\JsonLinesReader;
use App\Import\Reader\SourceRecord;
use PHPUnit\Framework\TestCase;

final class JsonLinesReaderTest extends TestCase
{
    use ImportFiles;

    private JsonLinesReader $reader;

    protected function setUp(): void
    {
        $this->reader = new JsonLinesReader();
    }

    protected function tearDown(): void
    {
        $this->removeImportFiles();
    }

    public function testSupportsJsonLinesFilesWhateverTheCase(): void
    {
        self::assertTrue($this->reader->supports('/tmp/cards.jsonl'));
        self::assertTrue($this->reader->supports('/tmp/CARDS.NDJSON'));
        self::assertFalse($this->reader->supports('/tmp/cards.json'));
        self::assertFalse($this->reader->supports('/tmp/cards.csv'));
    }

    public function testReadsOneRecordPerLineWithItsLineNumber(): void
    {
        $path = $this->jsonLinesFile([['name' => 'Fire Wyrm'], '', '   ', ['name' => 'Ice Wyrm']]);

        $records = $this->records($path);

        self::assertCount(2, $records);
        self::assertSame(1, $records[0]->position);
        self::assertSame(['name' => 'Fire Wyrm'], $records[0]->data);
        // Blank lines are skipped but still counted: the position is the
        // line a person would open the file at.
        self::assertSame(4, $records[1]->position);
        self::assertSame(['name' => 'Ice Wyrm'], $records[1]->data);
    }

    public function testReportsALineThatIsNotValidJsonAndKeepsReading(): void
    {
        $path = $this->jsonLinesFile(['{"name": "Fire Wyrm"', ['name' => 'Ice Wyrm']]);

        $records = $this->records($path);

        self::assertNull($records[0]->data);
        self::assertStringContainsString('not valid JSON', (string) $records[0]->error);
        self::assertSame(['name' => 'Ice Wyrm'], $records[1]->data);
    }

    public function testReportsALineThatIsNotAnObject(): void
    {
        $path = $this->jsonLinesFile(['["Fire Wyrm"]', '"Fire Wyrm"', '42']);

        foreach ($this->records($path) as $record) {
            self::assertNull($record->data);
            self::assertSame('The line is not a JSON object.', $record->error);
        }
    }

    public function testIgnoresAByteOrderMark(): void
    {
        $path = $this->jsonLinesFile(["\xEF\xBB\xBF".'{"name": "Fire Wyrm"}']);

        self::assertSame(['name' => 'Fire Wyrm'], $this->records($path)[0]->data);
    }

    /**
     * A line of several megabytes must not be loaded: it is reported, and
     * the next line is still read.
     */
    public function testReportsALineThatIsTooLongAndKeepsReading(): void
    {
        $path = $this->jsonLinesFile(['{"name": "'.str_repeat('a', 2_000_000).'"}', ['name' => 'Ice Wyrm']]);

        $records = $this->records($path);

        self::assertCount(2, $records);
        self::assertSame('The line is too long.', $records[0]->error);
        self::assertSame(2, $records[1]->position);
        self::assertSame(['name' => 'Ice Wyrm'], $records[1]->data);
    }

    public function testRefusesAMissingFileBeforeReadingAnything(): void
    {
        $this->expectException(UnreadableSourceException::class);

        // Not iterated: the failure must not wait for the first record.
        $this->reader->read('/nowhere/cards.jsonl');
    }

    /**
     * @return list<SourceRecord>
     */
    private function records(string $path): array
    {
        return iterator_to_array($this->reader->read($path), false);
    }
}
