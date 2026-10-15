<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Image\CardImages;
use App\Image\ImageResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the pictures attached to cards on this installation. Public, like
 * the catalog they illustrate.
 */
final class CustomCardImageController
{
    public function __construct(
        private readonly CardImages $cardImages,
    ) {
    }

    #[Route(
        '/api/custom-card-images/{size}/{name}',
        name: 'custom_card_image',
        requirements: ['size' => CardImages::SIZE_PATTERN, 'name' => CardImages::NAME_PATTERN],
        methods: ['GET'],
    )]
    public function show(string $size, string $name): Response
    {
        $image = $this->cardImages->find($size, $name) ?? throw new NotFoundHttpException('No such picture.');

        // The name comes from the content: another picture gets another name.
        return ImageResponse::of($image);
    }
}
