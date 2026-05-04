<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Helpers;

use DateTimeInterface;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

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
        QueryBuilder $qb,
        string       $sortField,
        string       $entityAlias = 'e',
        ?string      $cursor = null,
        int          $perPage = self::DEFAULT_PER_PAGE,
    ): CursorPaginationResult {
        $perPage = min( max( 1, $perPage ), self::MAX_PER_PAGE );

        $decoded = $cursor !== null ? self::decodeCursor( $cursor ) : null;
        $forward = $decoded === null || ( $decoded['dir'] ?? 'next' ) === 'next';

        if ( $decoded !== null ) {
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

            $qb->setParameter( '_cpf', $decoded['field'] )
               ->setParameter( '_cpi', $decoded['id'] );
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
                $nextCursor = self::encodeCursor( end( $items ), $sortField, isPrev: false );

            if ( $cursor !== null )
                $prevCursor = self::encodeCursor( reset( $items ), $sortField, isPrev: true );
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


    public static function decodeCursor( string $cursor ): array
    {
        $decoded = base64_decode( $cursor, strict: true );

        if ( $decoded === false )
            throw new InvalidArgumentException( 'Invalid cursor: not valid base64.' );

        $data = json_decode( $decoded, associative: true, flags: JSON_THROW_ON_ERROR );

        if ( !array_key_exists( 'field', $data ) || !isset( $data['id'] ) )
            throw new InvalidArgumentException( 'Invalid cursor: missing required keys.' );

        return $data;
    }


    private static function encodeCursor( object $entity, string $sortField, bool $isPrev ): string
    {
        $getter     = 'get' . ucfirst( $sortField );
        $fieldValue = method_exists( $entity, $getter ) ? $entity->$getter() : null;

        if ( $fieldValue instanceof DateTimeInterface )
            $fieldValue = $fieldValue->getTimestamp();

        return base64_encode( json_encode( [
            'field' => $fieldValue,
            'id'    => $entity->getId(),
            'dir'   => $isPrev ? 'prev' : 'next',
        ], JSON_THROW_ON_ERROR ) );
    }

}
