<?php /** @noinspection PhpClassHasTooManyDeclaredMembersInspection @noinspection PhpLackOfCohesionInspection */
declare( strict_types=1 );

/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Traits;

use PHP_SF\System\Classes\Abstracts\AbstractEntity;
use PHP_SF\System\Classes\Abstracts\AbstractEntityRepository;

trait EntityRepositoriesTrait
{

    private static array $repositories = [];


    public static function find( int $id ): AbstractEntity|null
    {
        return self::rep()->find( $id );
    }

    public static function findOneBy( array $criteria, array $orderBy = null ): AbstractEntity|null
    {
        return self::rep()->findOneBy( $criteria, $orderBy );
    }

    /**
     * @return array<AbstractEntity>
     */
    public static function findBy( array $criteria = [], array $orderBy = null, int $limit = null, int $offset = null ): array
    {
        $arr = self::rep()->findBy( $criteria, $orderBy, $limit, $offset );

        $res = [];
        foreach ( $arr as $item )
            $res[ $item->getId() ] = $item;

        return $res;
    }

    /**
     * @return array<AbstractEntity>
     */
    public static function findAll(): array
    {
        $arr = self::rep()->findAll();

        $res = [];
        foreach ( $arr as $item )
            $res[ $item->getId() ] = $item;

        return $res;
    }


    public static function rep(): AbstractEntityRepository
    {
        if ( array_key_exists( static::class, self::$repositories ) === false )
            self::setRepository();

        return self::$repositories[ static::class ];
    }

    private static function setRepository(): void
    {
        self::$repositories[ static::class ] = em()->getRepository( static::class );
    }

}
