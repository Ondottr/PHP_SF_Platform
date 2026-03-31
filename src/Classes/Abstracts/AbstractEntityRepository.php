<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use Doctrine\ORM\EntityRepository;

/**
 * Class AbstractEntityRepository
 *
 * @package PHP_SF\System\Classes\Abstracts
 */
abstract class AbstractEntityRepository extends EntityRepository
{

    final public function persist( AbstractEntity $entity, bool $flush = true ): void
    {
        $this->getEntityManager()->persist( $entity );

        if ( $flush )
            $this->getEntityManager()->flush( $entity );
    }

    final public function remove( AbstractEntity $entity, bool $flush = true ): void
    {
        $this->getEntityManager()->remove( $entity );

        if ( $flush )
            $this->getEntityManager()->flush( $entity );
    }

}
