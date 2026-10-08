<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Enum\ImportRunStatus;
use App\Import\ImportReport;
use App\Repository\ImportRunRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One execution of the import: what was imported, when, and what it did.
 * Kept as a history, to know where the catalog comes from and to find out
 * what went wrong with a file.
 */
#[ORM\Entity(repositoryClass: ImportRunRepository::class)]
#[ORM\Table(name: 'import_run')]
#[ORM\Index(name: 'import_run_started_at_idx', columns: ['started_at'])]
class ImportRun
{
    use UuidIdTrait;

    /**
     * Name of the imported file, without its directory.
     */
    #[ORM\Column(length: 255)]
    private string $source;

    #[ORM\Column(length: 20, enumType: ImportRunStatus::class)]
    private ImportRunStatus $status = ImportRunStatus::Running;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column]
    private int $createdCount = 0;

    #[ORM\Column]
    private int $updatedCount = 0;

    #[ORM\Column]
    private int $unchangedCount = 0;

    #[ORM\Column]
    private int $rejectedCount = 0;

    /**
     * The first rejected records, with the reasons. Every rejection is in the
     * log; this is what an administration screen can show.
     *
     * @var list<array{position: int, messages: list<string>}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    /**
     * Why the import stopped, when it failed.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $failure = null;

    public function __construct(string $source)
    {
        $this->id = Uuid::v7();
        $this->source = $source;
        $this->startedAt = new \DateTimeImmutable();
    }

    /**
     * Records how the import ended.
     */
    public function finish(ImportReport $report): void
    {
        $this->status = $report->getStatus();
        $this->finishedAt = new \DateTimeImmutable();
        $this->createdCount = $report->getCreated();
        $this->updatedCount = $report->getUpdated();
        $this->unchangedCount = $report->getUnchanged();
        $this->rejectedCount = $report->getRejected();
        $this->errors = $report->getErrors();
        $this->failure = $report->getFailure();
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getStatus(): ImportRunStatus
    {
        return $this->status;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getUnchangedCount(): int
    {
        return $this->unchangedCount;
    }

    public function getRejectedCount(): int
    {
        return $this->rejectedCount;
    }

    /**
     * @return list<array{position: int, messages: list<string>}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFailure(): ?string
    {
        return $this->failure;
    }
}
