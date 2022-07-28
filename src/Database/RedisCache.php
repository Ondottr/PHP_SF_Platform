<?php declare( strict_types=1 );

namespace PHP_SF\System\Database;

use Doctrine\Common\Cache\Cache;

class RedisCache implements Cache
{

    public function fetch( $id )
    {
        return rc()->get( $id );
    }

    public function contains( $id )
    {
        return rc()->exists( $id );
    }

    public function save( $id, $data, $lifeTime = null )
    {
        rc()->setex( $id, $data, $lifeTime ?? 1800 );
    }

    public function delete( $id )
    {
        return rc()->del( $id );
    }

    public function getStats(): array
    {
        return [];
    }
}