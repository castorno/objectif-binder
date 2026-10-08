<?php

declare(strict_types=1);

namespace App\Import\Exception;

final class ImportAlreadyRunningException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Another import is already running.');
    }
}
