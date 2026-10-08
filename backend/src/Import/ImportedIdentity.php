<?php

declare(strict_types=1);

namespace App\Import;

/**
 * What a card depicts or is, as an import source describes it.
 */
final readonly class ImportedIdentity
{
    public function __construct(
        /** Identifies the identity within its game, whatever the source file. */
        public string $externalId,
        public string $name,
        public ?int $sortOrder = null,
        /** A larger family the game sorts its identities into. */
        public ?string $groupName = null,
        public ?int $groupOrder = null,
    ) {
    }
}
