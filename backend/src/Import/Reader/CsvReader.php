<?php

declare(strict_types=1);

namespace App\Import\Reader;

use App\Import\Exception\UnreadableSourceException;

/**
 * Reads CSV files, as a spreadsheet exports them: a header line naming the
 * columns, then one card per line. A flat file cannot nest values the way
 * JSON does, so each line is rebuilt here into the shape every source hands
 * over; docs/import.md lists the columns.
 */
final class CsvReader implements RecordReader
{
    /** Where each plain column goes in a record. */
    private const array COLUMNS = [
        'game_slug' => ['game', 'slug'],
        'game_name' => ['game', 'name'],
        'game_identity_label' => ['game', 'identityLabel'],
        'set_code' => ['set', 'code'],
        'set_name' => ['set', 'name'],
        'set_release_date' => ['set', 'releaseDate'],
        'number' => ['number'],
        'name' => ['name'],
        'rarity' => ['rarity'],
        'external_id' => ['externalId'],
    ];

    /** A card can have several identities: these cells hold lists. */
    private const array IDENTITY_COLUMNS = ['identity_ids', 'identity_names', 'identity_sort_orders'];

    /** Left empty, these still reach the validation, which says they are required. */
    private const array REQUIRED_COLUMNS = ['game_slug', 'game_name', 'set_code', 'set_name', 'number', 'name'];

    /** "attribute:element" fills the "element" attribute of the card. */
    private const string ATTRIBUTE_PREFIX = 'attribute:';

    private const string LIST_SEPARATOR = '|';

    /** See JsonLinesReader: a longer line is not read whole into memory. */
    private const int MAX_LINE_BYTES = 1_048_576;

    private const string BYTE_ORDER_MARK = "\xEF\xBB\xBF";

    public function supports(string $path): bool
    {
        return 'csv' === strtolower(pathinfo($path, \PATHINFO_EXTENSION));
    }

    public function read(string $path): iterable
    {
        $file = is_file($path) && is_readable($path) ? @fopen($path, 'r') : false;
        if (false === $file) {
            throw new UnreadableSourceException(sprintf('The file "%s" cannot be read.', $path));
        }

        // The header is checked here, not in the generator below: a file
        // with a wrong column is refused once, when the import starts,
        // rather than line after line.
        try {
            $firstLine = fgets($file, self::MAX_LINE_BYTES);
            if (false === $firstLine || '' === trim($firstLine, " \t\r\n".self::BYTE_ORDER_MARK)) {
                throw new UnreadableSourceException('The file is empty: its first line must name the columns.');
            }
            if (str_starts_with($firstLine, self::BYTE_ORDER_MARK)) {
                $firstLine = substr($firstLine, \strlen(self::BYTE_ORDER_MARK));
            }

            // Spreadsheets set to French write semicolons where others write commas.
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $columns = $this->columns(str_getcsv($firstLine, $delimiter, '"', ''));
        } catch (UnreadableSourceException $exception) {
            fclose($file);

            throw $exception;
        }

        return $this->readLines($file, $delimiter, $columns);
    }

    /**
     * @param list<string|null> $header
     *
     * @return list<string>
     */
    private function columns(array $header): array
    {
        $columns = array_map(static fn (?string $name): string => strtolower(trim((string) $name)), $header);

        $unknown = array_filter($columns, fn (string $column): bool => !$this->isKnown($column));
        if ([] !== $unknown) {
            throw new UnreadableSourceException(sprintf('Unknown column: "%s".', implode('", "', $unknown)));
        }

        $repeated = array_keys(array_filter(array_count_values($columns), static fn (int $count): bool => $count > 1));
        if ([] !== $repeated) {
            throw new UnreadableSourceException(sprintf('Column named more than once: "%s".', implode('", "', $repeated)));
        }

        $missing = array_diff(self::REQUIRED_COLUMNS, $columns);
        if ([] !== $missing) {
            throw new UnreadableSourceException(sprintf('Missing column: "%s".', implode('", "', $missing)));
        }

        return $columns;
    }

    private function isKnown(string $column): bool
    {
        return isset(self::COLUMNS[$column])
            || \in_array($column, self::IDENTITY_COLUMNS, true)
            || (str_starts_with($column, self::ATTRIBUTE_PREFIX) && \strlen($column) > \strlen(self::ATTRIBUTE_PREFIX));
    }

    /**
     * @param resource     $file
     * @param list<string> $columns
     *
     * @return \Generator<SourceRecord>
     */
    private function readLines($file, string $delimiter, array $columns): \Generator
    {
        try {
            // The header was line 1.
            $position = 1;

            while (false !== $cells = fgetcsv($file, self::MAX_LINE_BYTES, $delimiter, '"', '')) {
                ++$position;

                // What fgetcsv returns for a blank line.
                if ([null] === $cells) {
                    continue;
                }

                if (\count($cells) !== \count($columns)) {
                    yield SourceRecord::unreadable($position, sprintf('The line has %d cells where the header names %d columns.', \count($cells), \count($columns)));

                    continue;
                }

                /** @var array<string, string> $row */
                $row = array_combine($columns, array_map(static fn (?string $cell): string => trim((string) $cell), $cells));

                if (!mb_check_encoding(implode('', $row), 'UTF-8')) {
                    yield SourceRecord::unreadable($position, 'The line is not encoded in UTF-8.');

                    continue;
                }

                yield $this->record($position, $row);
            }
        } finally {
            fclose($file);
        }
    }

    /**
     * @param array<string, string> $row cells by column name
     */
    private function record(int $position, array $row): SourceRecord
    {
        $record = [];

        foreach (self::COLUMNS as $column => $path) {
            $cell = $row[$column] ?? '';
            // An empty cell means "not given"; a required one is still
            // passed on, for the validation to report it by name.
            if ('' === $cell && !\in_array($column, self::REQUIRED_COLUMNS, true)) {
                continue;
            }

            if (1 === \count($path)) {
                $record[$path[0]] = $cell;
            } else {
                $record[$path[0]][$path[1]] = $cell;
            }
        }

        foreach ($row as $column => $cell) {
            if ('' !== $cell && str_starts_with($column, self::ATTRIBUTE_PREFIX)) {
                $record['attributes'][substr($column, \strlen(self::ATTRIBUTE_PREFIX))] = $cell;
            }
        }

        $ids = $this->list($row['identity_ids'] ?? '');
        $names = $this->list($row['identity_names'] ?? '');
        $sortOrders = $this->list($row['identity_sort_orders'] ?? '');

        if (\count($ids) !== \count($names) || ([] !== $sortOrders && \count($sortOrders) !== \count($ids))) {
            return SourceRecord::unreadable($position, 'The identity columns must list the same number of values, separated by "|".');
        }

        foreach ($ids as $index => $id) {
            $identity = ['externalId' => $id, 'name' => $names[$index]];

            $sortOrder = $sortOrders[$index] ?? '';
            if ('' !== $sortOrder) {
                // Every cell is text; anything that is not a whole number is
                // left as it is for the validation to refuse.
                $identity['sortOrder'] = ctype_digit($sortOrder) ? (int) $sortOrder : $sortOrder;
            }

            $record['identities'][] = $identity;
        }

        return SourceRecord::read($position, $record);
    }

    /**
     * @return list<string>
     */
    private function list(string $cell): array
    {
        return '' === $cell ? [] : array_map(trim(...), explode(self::LIST_SEPARATOR, $cell));
    }
}
