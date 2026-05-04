<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Helpers;

final readonly class CursorPaginationResult
{

    public function __construct(
        public array   $items,
        public ?string $cursor,
        public ?string $nextCursor,
        public ?string $prevCursor,
        public int     $perPage,
        public bool    $hasMore,
    ) {}


    public function getPaginationMeta(): array
    {
        return [
            'cursor'      => $this->cursor,
            'next_cursor' => $this->nextCursor,
            'prev_cursor' => $this->prevCursor,
            'per_page'    => $this->perPage,
            'has_more'    => $this->hasMore,
        ];
    }

}
