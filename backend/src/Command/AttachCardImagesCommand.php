<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Card;
use App\Exception\InvalidCardImageException;
use App\Image\CardImages;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cards:attach-images',
    description: 'Attaches pictures kept on this installation to cards that have none (see docs/import.md). Safe to run again on the same file.',
)]
final class AttachCardImagesCommand
{
    private const array COLUMNS = ['external_id', 'image_file'];

    /** How many refused lines are printed; the count tells the rest. */
    private const int PRINTED_ERRORS = 20;

    public function __construct(
        private readonly CardImages $cardImages,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'CSV file with the columns external_id and image_file, and small_image_file for a smaller rendition')]
        string $file,
        #[Option(description: 'Directory the paths of the file are relative to; that of the file by default')]
        ?string $directory = null,
    ): int {
        $handle = is_file($file) && is_readable($file) ? @fopen($file, 'r') : false;
        if (false === $handle) {
            $io->error(sprintf('Cannot read "%s".', $file));

            return Command::FAILURE;
        }

        $header = fgetcsv($handle, escape: '');
        if (!\is_array($header) || [] !== array_diff(self::COLUMNS, $header)) {
            fclose($handle);
            $io->error(sprintf('The first line of "%s" must name the columns %s.', $file, implode(' and ', self::COLUMNS)));

            return Command::FAILURE;
        }

        $header = array_map(strval(...), $header);
        $directory ??= \dirname($file);
        $attached = $unchanged = 0;
        $errors = [];

        for ($line = 2; \is_array($row = fgetcsv($handle, escape: '')); ++$line) {
            if ([null] === $row) {
                continue;
            }
            $values = array_combine($header, array_pad(\array_slice($row, 0, \count($header)), \count($header), ''));

            try {
                $this->attach($values, $directory) ? ++$attached : ++$unchanged;
            } catch (InvalidCardImageException $exception) {
                $errors[] = sprintf('  Line %d: %s', $line, $exception->getMessage());
            }
        }
        fclose($handle);

        $io->definitionList(['Attached' => $attached], ['Unchanged' => $unchanged], ['Refused' => \count($errors)]);
        $io->writeln(\array_slice($errors, 0, self::PRINTED_ERRORS));
        if (\count($errors) > self::PRINTED_ERRORS) {
            $io->writeln(sprintf('  ... and %d more.', \count($errors) - self::PRINTED_ERRORS));
        }

        if ([] !== $errors) {
            $io->warning('Finished, with refused lines.');

            return Command::FAILURE;
        }
        $io->success('Finished.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, ?string> $values a line of the file, by column
     *
     * @return bool whether anything changed
     *
     * @throws InvalidCardImageException
     */
    private function attach(array $values, string $directory): bool
    {
        $externalId = trim((string) $values['external_id']);
        $cards = '' === $externalId ? [] : $this->entityManager->getRepository(Card::class)->findBy(['externalId' => $externalId], limit: 2);
        if (1 !== \count($cards)) {
            throw new InvalidCardImageException(sprintf([] === $cards ? 'No card "%s".' : 'Several cards are named "%s".', $externalId));
        }

        $large = trim((string) $values['image_file']);
        $small = trim((string) ($values['small_image_file'] ?? ''));
        if ('' === $large) {
            throw new InvalidCardImageException('No picture given.');
        }

        return $this->cardImages->attach($cards[0], $directory.'/'.$large, '' === $small ? null : $directory.'/'.$small);
    }
}
