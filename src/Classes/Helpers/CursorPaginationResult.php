<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Helpers;

final readonly class CursorPaginationResult
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public array $items,
        public ?PaginationCursor $cursor,
        public ?PaginationCursor $nextCursor,
        public ?PaginationCursor $prevCursor,
        public int $perPage,
        public bool $hasMore,
    ) {
    }

    /**
     * @return array{cursor: ?string, next_cursor: ?string, prev_cursor: ?string, per_page: int, has_more: bool}
     */
    public function getPaginationMeta(): array
    {
        return [
            'cursor' => $this->cursor?->toString(),
            'next_cursor' => $this->nextCursor?->toString(),
            'prev_cursor' => $this->prevCursor?->toString(),
            'per_page' => $this->perPage,
            'has_more' => $this->hasMore,
        ];
    }
}
