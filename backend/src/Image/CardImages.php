<?php

declare(strict_types=1);

namespace App\Image;

use App\Entity\Card;
use App\Exception\InvalidCardImageException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The picture of a card: the one kept on this installation when it was
 * given one, or else the one its source serves.
 *
 * What the installation provides comes first, as for the picture of a set:
 * a source may have no picture of a card, or name one it does not serve.
 *
 * A picture kept here comes in two sizes, a small one for lists and a
 * large one for the page of the card, under one name made from its
 * content. Attaching the same file twice changes nothing. Like every
 * picture of a card, these are artwork owned by the publishers of the
 * games: they stay on the installation, outside the repository.
 */
final class CardImages
{
    public const int MAX_BYTES = 5 * 1024 * 1024;

    public const string SMALL_PATH = '/api/custom-card-images/small/';
    public const string LARGE_PATH = '/api/custom-card-images/large/';

    /** What a name made by attach() looks like: nothing else is ever read from the disk. */
    public const string NAME_PATTERN = '[0-9a-f]{32}\\.(?:png|jpg|webp)';

    public const string SIZE_PATTERN = 'small|large';

    /** The extension of the file kept, by the type found in its content. No SVG, which can carry script. */
    private const array EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(
        private readonly CardImageCache $cardImageCache,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/var/custom-card-images')]
        private readonly string $directory,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Where a browser should get the small picture of the card from; null
     * when it has none.
     */
    public function addressOf(Card $card): ?string
    {
        return null !== $card->getCustomImage()
            ? self::SMALL_PATH.$card->getCustomImage()
            : $this->cardImageCache->addressOf($card->getImageUrl());
    }

    /**
     * The same picture, larger, for the page of the card.
     */
    public function largeAddressOf(Card $card): ?string
    {
        return null !== $card->getCustomImage()
            ? self::LARGE_PATH.$card->getCustomImage()
            : $this->cardImageCache->addressOf($card->getLargeImageUrl());
    }

    /**
     * Keeps a copy of the given files as the picture of the card, in place
     * of the one kept for it before.
     *
     * @param string      $largeFile path of the picture
     * @param string|null $smallFile path of a smaller rendition of it, for lists; the picture itself when null
     *
     * @return bool whether anything changed
     *
     * @throws InvalidCardImageException when a file is not a picture this application keeps
     */
    public function attach(Card $card, string $largeFile, ?string $smallFile = null): bool
    {
        $extension = $this->extensionOf($largeFile);
        if (null !== $smallFile && $this->extensionOf($smallFile) !== $extension) {
            throw new InvalidCardImageException('The small picture is not of the same format as the large one.');
        }

        $name = md5_file($largeFile).'.'.$extension;
        $smallFile ??= $largeFile;
        $previous = $card->getCustomImage();
        $small = $this->find('small', $name);
        // The name says the large picture is the same; the small one is compared on its own.
        if ($previous === $name && null !== $this->find('large', $name) && null !== $small && md5_file($small['path']) === md5_file($smallFile)) {
            return false;
        }

        $this->filesystem->copy($largeFile, $this->directory.'/large/'.$name, true);
        $this->filesystem->copy($smallFile, $this->directory.'/small/'.$name, true);

        $card->setCustomImage($name);
        $this->entityManager->flush();

        if (null !== $previous && $previous !== $name) {
            $this->deleteUnlessUsed($previous);
        }

        return true;
    }

    /**
     * The file of a picture kept here, by its size and name.
     *
     * @return array{path: string, contentType: string}|null null when there is no such picture
     */
    public function find(string $size, string $name): ?array
    {
        if (1 !== preg_match('/^(?:'.self::SIZE_PATTERN.')$/', $size) || 1 !== preg_match('/^'.self::NAME_PATTERN.'$/', $name)) {
            return null;
        }

        $path = $this->directory.'/'.$size.'/'.$name;
        $contentType = array_search(pathinfo($name, \PATHINFO_EXTENSION), self::EXTENSIONS, true);

        return is_file($path) && false !== $contentType ? ['path' => $path, 'contentType' => $contentType] : null;
    }

    /**
     * @throws InvalidCardImageException
     */
    private function extensionOf(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new InvalidCardImageException(sprintf('There is no file "%s".', $file));
        }
        if (filesize($file) > self::MAX_BYTES) {
            throw new InvalidCardImageException(sprintf('"%s" is too large: 5 MB at most.', $file));
        }

        // What the content is, not what the name of the file says.
        $extension = self::EXTENSIONS[new \finfo(\FILEINFO_MIME_TYPE)->file($file)] ?? null;
        if (null === $extension || false === @getimagesize($file)) {
            throw new InvalidCardImageException(sprintf('"%s" is not a PNG, JPEG or WebP picture.', $file));
        }

        return $extension;
    }

    /** Two cards given the same file share its copy: it goes with the last of them. */
    private function deleteUnlessUsed(string $name): void
    {
        if (1 !== preg_match('/^'.self::NAME_PATTERN.'$/', $name) || $this->entityManager->getRepository(Card::class)->count(['customImage' => $name]) > 0) {
            return;
        }

        $this->filesystem->remove([$this->directory.'/small/'.$name, $this->directory.'/large/'.$name]);
    }
}
