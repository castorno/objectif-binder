<?php

declare(strict_types=1);

namespace App\Import\Reader;

/**
 * One entry read from an import source: its raw content, or why it could not
 * be read. A reader reports an unreadable entry instead of stopping, so one
 * bad line never costs the rest of the file.
 */
final readonly class SourceRecord
{
    /**
     * @param array<mixed>|null $data
     */
    private function __construct(
        /** Where the entry is in the source, for a person to find it: a line number in a file. */
        public int $position,
        public ?array $data,
        public ?string $error,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function read(int $position, array $data): self
    {
        return new self($position, $data, null);
    }

    public static function unreadable(int $position, string $error): self
    {
        return new self($position, null, $error);
    }
}
