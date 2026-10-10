<?php

declare(strict_types=1);

namespace App\Image;

/**
 * A picture kept by CardImageCache: where its copy is, and what it is.
 */
final readonly class CachedCardImage
{
    public function __construct(
        public string $path,
        public string $contentType,
        public ?string $etag,
    ) {
    }
}
