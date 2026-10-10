<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Image\CardImageCache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the copy of a card picture kept by CardImageCache. Public, like
 * the catalog the pictures belong to.
 */
final class CardImageController
{
    public function __construct(
        private readonly CardImageCache $cardImageCache,
    ) {
    }

    #[Route(
        '/api/card-images/{signature}/{address}',
        name: 'card_image',
        requirements: ['signature' => '[0-9a-f]{32}', 'address' => '[A-Za-z0-9_-]+'],
        methods: ['GET'],
    )]
    public function show(string $signature, string $address, Request $request): Response
    {
        // Not ours, not a picture, or not to be had right now: the page
        // shows its stand-in, and asks again another time.
        $image = $this->cardImageCache->find($signature, $address)
            ?? throw new NotFoundHttpException('No such picture.');

        $response = new BinaryFileResponse($image->path, headers: [
            'Content-Type' => $image->contentType,
            // Whatever the file is, a browser treats it as the picture it was declared to be.
            'X-Content-Type-Options' => 'nosniff',
        ]);
        // The address stands for one picture of one source: a browser can
        // keep it for as long as it likes.
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();
        if (null !== $image->etag) {
            $response->headers->set('ETag', $image->etag);
        }
        $response->isNotModified($request);

        return $response;
    }
}
