<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\CardFinish;

/**
 * One card as every import source hands it over, whatever the source: a file,
 * an API, a scraper. The importer only knows this shape, never where a card
 * came from.
 *
 * Built by ImportedCardFactory, which checks every field first: an instance
 * is always valid.
 */
final readonly class ImportedCard
{
    /**
     * @param array<string, mixed>   $attributes
     * @param list<ImportedIdentity> $identities
     * @param list<CardFinish>|null  $finishes   null when the source does not say
     */
    public function __construct(
        public string $gameSlug,
        public string $gameName,
        public ?string $gameIdentityLabel,
        public ?string $gameIdentityGroupLabel,
        public string $setCode,
        public string $setName,
        public ?\DateTimeImmutable $setReleaseDate,
        public string $number,
        public string $name,
        public ?string $rarity,
        public ?string $externalId,
        public ?string $imageUrl,
        public ?string $largeImageUrl,
        public array $attributes,
        public array $identities,
        public ?array $finishes = null,
    ) {
    }
}
