<?php

declare(strict_types=1);

namespace App\Import\Reader;

use App\Import\Exception\UnreadableSourceException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Reads the records of one kind of import file. Adding a format is adding an
 * implementation: nothing else in the import changes.
 */
#[AutoconfigureTag('app.import_reader')]
interface RecordReader
{
    public function supports(string $path): bool;

    /**
     * The records of the file, one at a time: the file is never loaded whole,
     * so memory use does not grow with its size.
     *
     * @return iterable<SourceRecord>
     *
     * @throws UnreadableSourceException if the file cannot be opened
     */
    public function read(string $path): iterable;
}
