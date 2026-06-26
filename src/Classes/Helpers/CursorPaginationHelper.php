<?php declare(strict_types=1);

namespace PHP_SF\System\Classes\Helpers;

use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

final class CursorPaginationHelper
{
    public const int DEFAULT_PER_PAGE = 20;

    public const int MAX_PER_PAGE = 100;

    // Allowlist pattern: DQL identifiers must be word characters only.
    // $sortField and $entityAlias are interpolated into DQL — they must never
    // come from user input. This guard is a last-resort safety net.
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';


    /**
     * Paginates a Doctrine QueryBuilder using cursor-based pagination.
     *
     * The QB must NOT have ORDER BY or setMaxResults applied — this method owns those clauses.
     * $sortField and $entityAlias are interpolated into DQL and MUST be hardcoded
     * trusted values, never derived from user input.
     * Sort field must be non-nullable for stable cursor behaviour.
     */
    public static function paginate(
        QueryBuilder $qb,
        string $sortField,
        string $entityAlias = 'e',
        ?PaginationCursor $cursor = null,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): CursorPaginationResult {
        if (!preg_match(self::IDENTIFIER_PATTERN, $sortField)) {
            throw new InvalidArgumentException(
                sprintf('Invalid sortField "%s": must be a valid DQL identifier.', $sortField),
            );
        }

        if (!preg_match(self::IDENTIFIER_PATTERN, $entityAlias)) {
            throw new InvalidArgumentException(
                sprintf('Invalid entityAlias "%s": must be a valid DQL identifier.', $entityAlias),
            );
        }

        $perPage = min(max(1, $perPage), self::MAX_PER_PAGE);
        $forward = null === $cursor || $cursor->isForward;

        if (null !== $cursor) {
            $expr = $qb->expr();

            if ($forward) {
                $qb->andWhere($expr->orX(
                    $expr->gt("{$entityAlias}.{$sortField}", ':_cpf'),
                    $expr->andX(
                        $expr->eq("{$entityAlias}.{$sortField}", ':_cpf'),
                        $expr->gt("{$entityAlias}.id", ':_cpi'),
                    ),
                ));
            } else {
                $qb->andWhere($expr->orX(
                    $expr->lt("{$entityAlias}.{$sortField}", ':_cpf'),
                    $expr->andX(
                        $expr->eq("{$entityAlias}.{$sortField}", ':_cpf'),
                        $expr->lt("{$entityAlias}.id", ':_cpi'),
                    ),
                ));
            }

            $qb->setParameter('_cpf', $cursor->field)
                ->setParameter('_cpi', $cursor->id);
        }

        $dir = $forward ? 'ASC' : 'DESC';

        $qb->orderBy("{$entityAlias}.{$sortField}", $dir)
            ->addOrderBy("{$entityAlias}.id", $dir)
            ->setMaxResults($perPage + 1);

        $items = $qb->getQuery()->getResult();

        $hasMore = count($items) > $perPage;
        if ($hasMore) {
            array_pop($items);
        }

        if (!$forward) {
            $items = array_reverse($items);
        }

        $nextCursor = null;
        $prevCursor = null;

        if (!empty($items)) {
            if ($forward) {
                if ($hasMore) {
                    $nextCursor = PaginationCursor::after(end($items), $sortField);
                }

                if (null !== $cursor) {
                    $prevCursor = PaginationCursor::before(reset($items), $sortField);
                }
            } else {
                // Backward pass: next is always known (we came from forward),
                // prev only exists if there are more items further back.
                $nextCursor = PaginationCursor::after(end($items), $sortField);

                if ($hasMore) {
                    $prevCursor = PaginationCursor::before(reset($items), $sortField);
                }
            }
        }

        return new CursorPaginationResult(
            items: $items,
            cursor: $cursor,
            nextCursor: $nextCursor,
            prevCursor: $prevCursor,
            perPage: $perPage,
            hasMore: $hasMore,
        );
    }
}
