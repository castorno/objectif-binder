<?php

declare(strict_types=1);

namespace App\Image;

/**
 * A picture kept on this installation: where its file is, and what it is.
 */
final readonly class StoredImage
{
    public function __construct(
        public string $path,
        public string $contentType,
        public ?string $etag = null,
    ) {
    }
}
