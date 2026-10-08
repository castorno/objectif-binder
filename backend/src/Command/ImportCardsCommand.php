<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\ImportRunStatus;
use App\Import\Exception\ImportAlreadyRunningException;
use App\Import\Exception\UnreadableSourceException;
use App\Import\ImportReport;
use App\Import\ImportRunner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import',
    description: 'Imports cards into the catalog from a file (JSON Lines). Safe to run again on the same file.',
)]
final class ImportCardsCommand
{
    public function __construct(
        private readonly ImportRunner $importRunner,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Path of the file to import; its format is described in docs/import.md')]
        string $file,
        #[Option(description: 'Check the file and report what would change, without writing anything')]
        bool $dryRun = false,
    ): int {
        try {
            $report = $this->importRunner->run($file, $dryRun);
        } catch (UnreadableSourceException|ImportAlreadyRunningException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->printReport($io, $report);

        return ImportRunStatus::Completed === $report->getStatus() ? Command::SUCCESS : Command::FAILURE;
    }

    private function printReport(SymfonyStyle $io, ImportReport $report): void
    {
        $io->definitionList(
            ['Created' => $report->getCreated()],
            ['Updated' => $report->getUpdated()],
            ['Unchanged' => $report->getUnchanged()],
            ['Rejected' => $report->getRejected()],
        );

        foreach ($report->getErrors() as $error) {
            $io->writeln(sprintf('  Line %d: %s', $error['position'], implode(' ', $error['messages'])));
        }
        if ($report->getRejected() > \count($report->getErrors())) {
            $io->writeln(sprintf('  ... and %d more, all in the log.', $report->getRejected() - \count($report->getErrors())));
        }

        if (ImportRunStatus::Failed === $report->getStatus()) {
            $io->error(sprintf('The import stopped: %s Cards imported before the error are kept; run the file again once it is fixed.', $report->getFailure()));

            return;
        }

        if ($report->dryRun) {
            $io->success('Dry run: nothing was written.');
        } elseif ($report->getRejected() > 0) {
            $io->warning('Import finished, with rejected lines.');
        } else {
            $io->success('Import finished.');
        }
    }
}
