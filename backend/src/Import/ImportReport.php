<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\ImportRunStatus;

/**
 * What an import did, counted as it goes.
 */
final class ImportReport
{
    /** Rejected records are all counted and logged; only the first ones are kept here. */
    public const int MAX_ERRORS_KEPT = 50;

    private int $created = 0;
    private int $updated = 0;
    private int $unchanged = 0;
    private int $rejected = 0;
    /** @var list<array{position: int, messages: list<string>}> */
    private array $errors = [];
    private ImportRunStatus $status = ImportRunStatus::Running;
    private ?string $failure = null;

    public function __construct(
        public readonly string $source,
        /** Nothing was written: the counts say what a real import would do. */
        public readonly bool $dryRun,
    ) {
    }

    public function count(ImportOutcome $outcome): void
    {
        match ($outcome) {
            ImportOutcome::Created => ++$this->created,
            ImportOutcome::Updated => ++$this->updated,
            ImportOutcome::Unchanged => ++$this->unchanged,
        };
    }

    /**
     * @param list<string> $messages
     */
    public function reject(int $position, array $messages): void
    {
        ++$this->rejected;

        if (\count($this->errors) < self::MAX_ERRORS_KEPT) {
            $this->errors[] = ['position' => $position, 'messages' => $messages];
        }
    }

    public function complete(): void
    {
        $this->status = ImportRunStatus::Completed;
    }

    public function fail(string $failure): void
    {
        $this->status = ImportRunStatus::Failed;
        $this->failure = $failure;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getUnchanged(): int
    {
        return $this->unchanged;
    }

    public function getRejected(): int
    {
        return $this->rejected;
    }

    /**
     * @return list<array{position: int, messages: list<string>}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatus(): ImportRunStatus
    {
        return $this->status;
    }

    public function getFailure(): ?string
    {
        return $this->failure;
    }
}
