<?php

declare(strict_types=1);

namespace App\Exception;

final class CollectionEntryConflictException extends \DomainException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('This collection entry was changed by another request. Please try again.', previous: $previous);
    }
}
