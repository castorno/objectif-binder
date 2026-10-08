<?php

declare(strict_types=1);

namespace App\Enum;

enum ImportRunStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    /** Stopped by an error; what earlier batches wrote stays in the catalog. */
    case Failed = 'failed';
}
