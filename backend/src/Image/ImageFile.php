<?php

declare(strict_types=1);

namespace App\Image;

/**
 * What a picture kept by this application may be, and how to tell.
 *
 * Only PNG, JPEG and WebP: no SVG, which can carry script. A file is judged
 * by its content, never by its name or by the type it claims.
 */
final class ImageFile
{
    /** The extension of the file kept, by the type found in its content. */
    private const array EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /**
     * The extension to keep the file under; null when it is not a picture
     * this application keeps.
     */
    public static function extensionOf(string $path): ?string
    {
        $extension = self::EXTENSIONS[new \finfo(\FILEINFO_MIME_TYPE)->file($path)] ?? null;

        return null !== $extension && false !== @getimagesize($path) ? $extension : null;
    }

    /**
     * The type of a file kept under the given name, by its extension; null
     * for an extension this application never gives.
     */
    public static function contentTypeOf(string $name): ?string
    {
        $contentType = array_search(pathinfo($name, \PATHINFO_EXTENSION), self::EXTENSIONS, true);

        return false === $contentType ? null : $contentType;
    }
}
