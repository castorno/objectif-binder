<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\Source\Tcgdex\TcgdexException;
use App\Import\Source\Tcgdex\TcgdexFetcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:fetch-tcgdex',
    description: 'Downloads Pokémon sets from TCGdex into files that app:import can load. Writes nothing to the catalog.',
)]
final class FetchTcgdexCommand
{
    public function __construct(
        private readonly TcgdexFetcher $fetcher,
    ) {
    }

    /**
     * @param list<string> $set
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Id of a set to download, as TCGdex names it (e.g. "swsh3"); can be given several times')]
        array $set = [],
        #[Option(description: 'Download every set; those already downloaded are skipped')]
        bool $all = false,
        #[Option(description: 'Download again what is already there')]
        bool $refresh = false,
        #[Option(description: 'Also keep the address of each card picture, shown from the TCGdex servers. The pictures are copyrighted artwork: read docs/import.md first')]
        bool $withImages = false,
    ): int {
        if ($all === ([] !== $set)) {
            $io->error('Name the sets to download with --set, or ask for all of them with --all.');

            return Command::INVALID;
        }

        try {
            $setIds = $all ? $this->fetcher->availableSetIds() : array_values(array_unique($set));

            foreach ($setIds as $setId) {
                $result = $this->fetcher->fetchSet($setId, $refresh, $withImages);

                if (!$result->downloaded) {
                    $io->writeln(sprintf('  %s: already downloaded, skipped.', $setId));
                } elseif (!$result->isComplete()) {
                    $io->warning(sprintf('%s: %d cards downloaded, where TCGdex announces %d.', $setId, $result->cardCount, (int) $result->expectedCardCount));
                } else {
                    $io->writeln(sprintf('  %s: %d cards.', $setId, $result->cardCount));
                }
            }
        } catch (TcgdexException $exception) {
            // Stops at the first failure rather than insisting on a service
            // that is struggling; what is downloaded is kept.
            $io->error($exception->getMessage());
            $io->writeln('Sets already downloaded are kept: run the command again to carry on.');

            return Command::FAILURE;
        }

        $io->success('Done. Load a set with: bin/console app:import var/import/tcgdex/<set>.jsonl');

        return Command::SUCCESS;
    }
}
