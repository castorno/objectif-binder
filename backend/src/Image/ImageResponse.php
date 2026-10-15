<?php

declare(strict_types=1);

namespace App\Image;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * How every picture kept here is served.
 */
final class ImageResponse
{
    /**
     * Each address stands for one picture that never changes (a new one
     * gets a new address): a browser can keep it for as long as it likes.
     */
    public static function of(StoredImage $image): BinaryFileResponse
    {
        $response = new BinaryFileResponse($image->path, headers: [
            'Content-Type' => $image->contentType,
            // Whatever the file is, a browser treats it as the picture it was declared to be.
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();
        if (null !== $image->etag) {
            $response->headers->set('ETag', $image->etag);
        }

        return $response;
    }
}
