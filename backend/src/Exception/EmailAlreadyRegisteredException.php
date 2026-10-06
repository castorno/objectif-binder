<?php

declare(strict_types=1);

namespace App\Exception;

final class EmailAlreadyRegisteredException extends \DomainException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('This e-mail address is already registered.', previous: $previous);
    }
}
