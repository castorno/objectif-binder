<?php

declare(strict_types=1);

namespace App\Import\Reader;

use App\Import\Exception\UnreadableSourceException;

/**
 * Reads JSON Lines files: one JSON object per line.
 */
final class JsonLinesReader implements RecordReader
{
    /**
     * A card is a few hundred bytes. A much longer line is a broken or
     * hostile file, and is not read into memory.
     */
    private const int MAX_LINE_BYTES = 1_048_576;

    private const string BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    public function supports(string $path): bool
    {
        return \in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), ['jsonl', 'ndjson'], true);
    }

    public function read(string $path): iterable
    {
        // Opened here, not in the generator below: a missing file is reported
        // when the import starts, not when its first line is asked for.
        $file = is_file($path) && is_readable($path) ? @fopen($path, 'r') : false;
        if (false === $file) {
            throw new UnreadableSourceException(sprintf('The file "%s" cannot be read.', $path));
        }

        return $this->readLines($file);
    }

    /**
     * @param resource $file
     *
     * @return \Generator<SourceRecord>
     */
    private function readLines($file): \Generator
    {
        try {
            $position = 0;

            while (false !== $line = fgets($file, self::MAX_LINE_BYTES + 2)) {
                ++$position;

                if (\strlen($line) > self::MAX_LINE_BYTES && !str_ends_with($line, "\n")) {
                    $this->skipToEndOfLine($file);
                    yield SourceRecord::unreadable($position, 'The line is too long.');

                    continue;
                }

                if (1 === $position && str_starts_with($line, self::BYTE_ORDER_MARK)) {
                    $line = substr($line, \strlen(self::BYTE_ORDER_MARK));
                }

                $line = trim($line);
                if ('' === $line) {
                    continue;
                }

                yield $this->decode($position, $line);
            }
        } finally {
            fclose($file);
        }
    }

    private function decode(int $position, string $line): SourceRecord
    {
        if (!str_starts_with($line, '{')) {
            return SourceRecord::unreadable($position, 'The line is not a JSON object.');
        }

        try {
            $data = json_decode($line, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return SourceRecord::unreadable($position, sprintf('The line is not valid JSON: %s.', $exception->getMessage()));
        }

        return SourceRecord::read($position, (array) $data);
    }

    /**
     * @param resource $file
     */
    private function skipToEndOfLine($file): void
    {
        do {
            $rest = fgets($file, 8192);
        } while (false !== $rest && !str_ends_with($rest, "\n"));
    }
}
