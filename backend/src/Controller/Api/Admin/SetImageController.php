<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\CardSet;
use App\Exception\InvalidSetImageException;
use App\Image\SetImages;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The picture an administrator gives a set, for one that has no logo or
 * whose logo does not suit.
 */
final class SetImageController
{
    public function __construct(
        private readonly SetImages $setImages,
    ) {
    }

    /**
     * The picture comes as a file of a form, under the name "image".
     */
    #[Route('/api/admin/sets/{id}/image', name: 'admin_set_image_post', methods: ['POST'])]
    public function upload(CardSet $cardSet, Request $request): JsonResponse
    {
        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('Send the picture as a file named "image".');
        }

        try {
            $this->setImages->store($cardSet, $file);
        } catch (InvalidSetImageException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse(['imageUrl' => $this->setImages->addressOf($cardSet)]);
    }

    #[Route('/api/admin/sets/{id}/image', name: 'admin_set_image_delete', methods: ['DELETE'])]
    public function remove(CardSet $cardSet): JsonResponse
    {
        $this->setImages->remove($cardSet);

        // What stands for the set now: its imported logo, or nothing.
        return new JsonResponse(['imageUrl' => $this->setImages->addressOf($cardSet)]);
    }
}
