<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Image\ImageResponse;
use App\Image\SetImages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the pictures administrators sent for sets. Public, like the
 * catalog they illustrate.
 */
final class SetImageController
{
    public function __construct(
        private readonly SetImages $setImages,
    ) {
    }

    #[Route('/api/set-images/{name}', name: 'set_image', requirements: ['name' => SetImages::NAME_PATTERN], methods: ['GET'])]
    public function show(string $name): Response
    {
        $image = $this->setImages->find($name) ?? throw new NotFoundHttpException('No such picture.');

        // A new picture gets a new name: this one never changes.
        return ImageResponse::of($image);
    }
}
