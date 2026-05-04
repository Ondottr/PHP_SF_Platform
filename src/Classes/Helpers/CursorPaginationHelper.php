<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Helpers;

use Doctrine\ORM\QueryBuilder;

final class CursorPaginationHelper
{

    public const int DEFAULT_PER_PAGE = 20;
    public const int MAX_PER_PAGE     = 100;


    /**
     * Paginates a Doctrine QueryBuilder using cursor-based pagination.
     *
     * The QB must NOT have ORDER BY or setMaxResults applied — this method owns those clauses.
     * Sort field must be non-nullable for stable cursor behaviour.
     */
    public static function paginate(
        QueryBuilder     $qb,
        string           $sortField,
        string           $entityAlias = 'e',
        ?PaginationCursor $cursor      = null,
        int              $perPage     = self::DEFAULT_PER_PAGE,
    ): CursorPaginationResult {
        $perPage   = min( max( 1, $perPage ), self::MAX_PER_PAGE );
        $forward   = $cursor === null || $cursor->isForward;

        if ( $cursor !== null ) {
            $expr = $qb->expr();

            if ( $forward ) {
                $qb->andWhere( $expr->orX(
                    $expr->gt( "{$entityAlias}.{$sortField}", ':_cpf' ),
                    $expr->andX(
                        $expr->eq( "{$entityAlias}.{$sortField}", ':_cpf' ),
                        $expr->gt( "{$entityAlias}.id", ':_cpi' ),
                    ),
                ) );
            } else {
                $qb->andWhere( $expr->orX(
                    $expr->lt( "{$entityAlias}.{$sortField}", ':_cpf' ),
                    $expr->andX(
                        $expr->eq( "{$entityAlias}.{$sortField}", ':_cpf' ),
                        $expr->lt( "{$entityAlias}.id", ':_cpi' ),
                    ),
                ) );
            }

            $qb->setParameter( '_cpf', $cursor->field )
               ->setParameter( '_cpi', $cursor->id );
        }

        $dir = $forward ? 'ASC' : 'DESC';

        $qb->orderBy( "{$entityAlias}.{$sortField}", $dir )
           ->addOrderBy( "{$entityAlias}.id", $dir )
           ->setMaxResults( $perPage + 1 );

        $items = $qb->getQuery()->getResult();

        $hasMore = count( $items ) > $perPage;
        if ( $hasMore )
            array_pop( $items );

        if ( !$forward )
            $items = array_reverse( $items );

        $nextCursor = null;
        $prevCursor = null;

        if ( !empty( $items ) ) {
            if ( $hasMore )
                $nextCursor = PaginationCursor::after( end( $items ), $sortField );

            if ( $cursor !== null )
                $prevCursor = PaginationCursor::before( reset( $items ), $sortField );
        }

        return new CursorPaginationResult(
            items:      $items,
            cursor:     $cursor,
            nextCursor: $nextCursor,
            prevCursor: $prevCursor,
            perPage:    $perPage,
            hasMore:    $hasMore,
        );
    }

}
