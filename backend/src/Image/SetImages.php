<?php

declare(strict_types=1);

namespace App\Image;

use App\Entity\CardSet;
use App\Exception\InvalidSetImageException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The picture that stands for a set: the one an administrator sent, or
 * else the logo imported with the set.
 *
 * A file sent from a browser is the least trustworthy thing this
 * application receives. What is kept of it:
 *
 * - nothing of its name: the file is stored under a name made here;
 * - nothing of the type it claims: its content is looked at, and only PNG,
 *   JPEG and WebP pictures are kept. No SVG, which can carry script;
 * - no more than MAX_BYTES.
 *
 * The pictures stay on the installation, outside the repository.
 */
final class SetImages
{
    public const int MAX_BYTES = 2 * 1024 * 1024;

    /** What a name made by store() looks like: nothing else is ever read from the disk. */
    public const string NAME_PATTERN = '[0-9a-f]{32}\\.(?:png|jpg|webp)';

    public function __construct(
        private readonly CardImageCache $cardImageCache,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/var/set-images')]
        private readonly string $directory,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Where a browser should get the picture of the set from; null when it
     * has none. The logo imported with the set is served like the pictures
     * of the cards: from its copy here when copies are kept.
     */
    public function addressOf(CardSet $cardSet): ?string
    {
        return null !== $cardSet->getCustomImage()
            ? '/api/set-images/'.$cardSet->getCustomImage()
            : $this->cardImageCache->addressOf($cardSet->getLogoUrl());
    }

    /**
     * Keeps a picture sent for the set, in place of the one it had.
     *
     * @throws InvalidSetImageException when the file is not a picture this application keeps
     */
    public function store(CardSet $cardSet, UploadedFile $file): void
    {
        // Also true of a file the server itself refused for its size.
        if (!$file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidSetImageException('The picture is too large: 2 MB at most.');
        }

        // What the content is, not what the browser said it was.
        $extension = ImageFile::extensionOf($file->getPathname());
        if (null === $extension) {
            throw new InvalidSetImageException('The file is not a PNG, JPEG or WebP picture.');
        }

        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $this->filesystem->mkdir($this->directory);
        $file->move($this->directory, $name);

        $previous = $cardSet->getCustomImage();
        $cardSet->setCustomImage($name);
        $this->entityManager->flush();
        $this->delete($previous);
    }

    /**
     * Takes the picture sent for the set away: its imported logo, if it has
     * one, stands for it again.
     */
    public function remove(CardSet $cardSet): void
    {
        $previous = $cardSet->getCustomImage();
        $cardSet->setCustomImage(null);
        $this->entityManager->flush();
        $this->delete($previous);
    }

    /**
     * The file of a picture kept here, by its name.
     *
     * @return StoredImage|null null when there is no such picture
     */
    public function find(string $name): ?StoredImage
    {
        if (1 !== preg_match('/^'.self::NAME_PATTERN.'$/', $name)) {
            return null;
        }

        $path = $this->directory.'/'.$name;
        $contentType = ImageFile::contentTypeOf($name);

        return is_file($path) && null !== $contentType ? new StoredImage($path, $contentType) : null;
    }

    private function delete(?string $name): void
    {
        if (null !== $name && 1 === preg_match('/^'.self::NAME_PATTERN.'$/', $name)) {
            $this->filesystem->remove($this->directory.'/'.$name);
        }
    }
}
