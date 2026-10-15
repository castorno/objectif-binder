<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * One page of a list, with where it stands in the whole: the shape every
 * paginated route answers with, `Paginated<T>` on the client.
 */
final readonly class PaginatedDto implements \JsonSerializable
{
    /**
     * @param list<mixed>          $data      the entries of the page
     * @param array<string, mixed> $extraMeta what a route says about the search besides the page
     */
    public function __construct(
        public array $data,
        public int $total,
        public int $page,
        public int $limit,
        public array $extraMeta = [],
    ) {
    }

    /**
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data,
            'meta' => [
                'total' => $this->total,
                'page' => $this->page,
                'limit' => $this->limit,
                'totalPages' => (int) ceil($this->total / $this->limit),
                ...$this->extraMeta,
            ],
        ];
    }
}
