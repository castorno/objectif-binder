<?php

declare(strict_types=1);

namespace App\Import\Exception;

/**
 * A record of an import source that cannot become a card.
 */
final class InvalidRecordException extends \DomainException
{
    /**
     * @param non-empty-list<string> $messages one per problem found
     */
    public function __construct(
        public readonly array $messages,
    ) {
        parent::__construct(implode(' ', $messages));
    }
}
