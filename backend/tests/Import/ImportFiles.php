<?php

declare(strict_types=1);

namespace App\Tests\Import;

/**
 * Writes the import files a test needs, and removes them afterwards.
 */
trait ImportFiles
{
    /** @var list<string> */
    private array $importFiles = [];

    /**
     * @param list<array<mixed>|string> $lines arrays are written as JSON, strings as they are
     */
    private function jsonLinesFile(array $lines, string $extension = 'jsonl'): string
    {
        return $this->importFile(implode("\n", array_map(
            static fn (array|string $line): string => \is_string($line) ? $line : json_encode($line, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            $lines,
        ))."\n", $extension);
    }

    private function importFile(string $content, string $extension): string
    {
        $path = sys_get_temp_dir().'/objectif-binder-test-'.bin2hex(random_bytes(6)).'.'.$extension;
        file_put_contents($path, $content);

        return $this->importFiles[] = $path;
    }

    private function removeImportFiles(): void
    {
        foreach ($this->importFiles as $path) {
            @unlink($path);
        }
        $this->importFiles = [];
    }

    /**
     * A valid record; $overrides replaces whole top-level fields, and a null
     * value removes the field.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function cardRecord(string $gameSlug, string $number, string $name, array $overrides = []): array
    {
        $record = array_merge([
            'game' => ['slug' => $gameSlug, 'name' => 'Import Test', 'identityLabel' => 'Creatures'],
            'set' => ['code' => 'IT1', 'name' => 'First Set', 'releaseDate' => '2025-03-01'],
            'number' => $number,
            'name' => $name,
            'rarity' => 'Common',
            'externalId' => 'it1-'.$number,
            'attributes' => ['type' => 'Creature'],
            'identities' => [['externalId' => 'wyrm', 'name' => 'Wyrm', 'sortOrder' => 1]],
        ], $overrides);

        return array_filter($record, static fn (mixed $value): bool => null !== $value);
    }
}
