<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\ImportRun;
use App\Import\Exception\ImportAlreadyRunningException;
use App\Import\Exception\InvalidRecordException;
use App\Import\Exception\UnreadableSourceException;
use App\Import\Reader\RecordReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Lock\LockFactory;

/**
 * Runs an import from start to end: reads the file, checks each record,
 * hands the valid ones to the importer in batches, and keeps a trace of what
 * happened.
 */
final class ImportRunner
{
    public const int DEFAULT_BATCH_SIZE = 200;

    /**
     * @param iterable<RecordReader> $readers
     */
    public function __construct(
        #[AutowireIterator('app.import_reader')]
        private readonly iterable $readers,
        private readonly ImportedCardFactory $cardFactory,
        private readonly CardImporter $importer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $managerRegistry,
        private readonly LockFactory $lockFactory,
        // The "import" log channel: see config/packages/monolog.yaml.
        #[Target('import')]
        private readonly LoggerInterface $importLogger,
    ) {
    }

    /**
     * A record that cannot be imported is counted, logged and skipped. An
     * error that stops the import leaves what earlier batches wrote in place:
     * running the same file again picks up where it stopped.
     *
     * @param bool $dryRun does everything, then undoes it: the report says
     *                     what a real import would do
     *
     * @throws UnreadableSourceException     if the file cannot be read or its format is unknown
     * @throws ImportAlreadyRunningException
     */
    public function run(string $path, bool $dryRun = false, int $batchSize = self::DEFAULT_BATCH_SIZE): ImportReport
    {
        $records = $this->readerFor($path)->read($path);

        // Two imports at once would both create the set they both find
        // missing, and one would fail on the unique index.
        $lock = $this->lockFactory->createLock('card-import');
        if (!$lock->acquire()) {
            throw new ImportAlreadyRunningException();
        }

        try {
            return $this->import($records, new ImportReport(basename($path), $dryRun), max(1, $batchSize));
        } finally {
            $lock->release();
        }
    }

    /**
     * @param iterable<Reader\SourceRecord> $records
     */
    private function import(iterable $records, ImportReport $report, int $batchSize): ImportReport
    {
        $this->importLogger->info('Import started.', ['source' => $report->source, 'dryRun' => $report->dryRun]);

        $connection = $this->entityManager->getConnection();
        $runId = null;

        if ($report->dryRun) {
            $connection->beginTransaction();
        } else {
            $run = new ImportRun($report->source);
            $this->entityManager->persist($run);
            $this->entityManager->flush();
            $runId = $run->getId();
        }

        try {
            $pending = 0;

            foreach ($records as $record) {
                if (null === $record->data) {
                    $this->reject($report, $record->position, [$record->error ?? 'The record cannot be read.']);

                    continue;
                }

                try {
                    $card = $this->cardFactory->fromArray($record->data);
                } catch (InvalidRecordException $exception) {
                    $this->reject($report, $record->position, $exception->messages);

                    continue;
                }

                $report->count($this->importer->import($card));

                if (++$pending >= $batchSize) {
                    $this->importer->flush();
                    $pending = 0;
                }
            }

            $this->importer->flush();
            $report->complete();
        } catch (\Throwable $exception) {
            $report->fail($exception->getMessage());
            // Drop the batch that was being prepared: recording how the
            // import ended must not write half of it.
            $this->importer->reset();
            $this->entityManager->clear();
            $this->importLogger->error('Import failed.', ['source' => $report->source, 'exception' => $exception]);
        } finally {
            if ($report->dryRun) {
                $connection->rollBack();
                $this->entityManager->clear();
            }
        }

        if (null !== $runId) {
            // A database error closes the entity manager: a fresh one is
            // needed to record how the import ended.
            if (!$this->entityManager->isOpen()) {
                $this->managerRegistry->resetManager();
            }

            $this->entityManager->find(ImportRun::class, $runId)?->finish($report);
            $this->entityManager->flush();
        }

        $this->importLogger->info('Import finished.', [
            'source' => $report->source,
            'dryRun' => $report->dryRun,
            'status' => $report->getStatus()->value,
            'created' => $report->getCreated(),
            'updated' => $report->getUpdated(),
            'unchanged' => $report->getUnchanged(),
            'rejected' => $report->getRejected(),
        ]);

        return $report;
    }

    /**
     * @param list<string> $messages
     */
    private function reject(ImportReport $report, int $position, array $messages): void
    {
        $report->reject($position, $messages);
        $this->importLogger->warning('Record rejected.', ['source' => $report->source, 'position' => $position, 'messages' => $messages]);
    }

    private function readerFor(string $path): RecordReader
    {
        foreach ($this->readers as $reader) {
            if ($reader->supports($path)) {
                return $reader;
            }
        }

        throw new UnreadableSourceException(sprintf('No reader knows the format of "%s".', basename($path)));
    }
}
