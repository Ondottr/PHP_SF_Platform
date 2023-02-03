<?php /** @noinspection MagicMethodsValidityInspection */
declare( strict_types=1 );
/**
 *  Copyright © 2018-2023, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 *  Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 *  granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 *  THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 *  INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 *  LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 *  RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 *  TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Core;

use Doctrine\Common\EventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Classes\Abstracts\AbstractDoctrineLifecycleCallback;
use PHP_SF\System\Interface\DoctrineCallbacksLoaderInterface;
use function array_key_exists;
use function in_array;

abstract class DoctrineCallbacksLoader implements DoctrineCallbacksLoaderInterface
{

    private const AVAILABLE_CALLBACKS = [
        Events::postRemove,
        Events::preRemove,
        Events::postPersist,
        Events::postLoad,
        Events::preFlush,
        Events::prePersist,
        Events::preUpdate,
        Events::postUpdate,
    ];

    #[ORM\PreFlush]
    final public function __preFlush( EventArgs $args ): void
    {
        $this->getCallbackClass( Events::preFlush, $args )?->callback();
    }

    final public function getCallbackClass( string $callback, EventArgs $args ): AbstractDoctrineLifecycleCallback|null
    {
        if ( in_array( $callback, self::AVAILABLE_CALLBACKS, true ) === false ||
            array_key_exists( $callback, $this->getLifecycleCallbacks() ) === false
        )
            return null;

        return new ( $this->getLifecycleCallbacks()[ $callback ] )( $this, $args );
    }

    #[ORM\PreRemove]
    final public function __preRemove( EventArgs $args ): void
    {
        $this->getCallbackClass( Events::preRemove, $args )?->callback();
    }

    #[ORM\PrePersist]
    final public function __prePersist( EventArgs $args ): void
    {
        $this->getCallbackClass( Events::prePersist, $args )?->callback();
    }

    #[ORM\PreUpdate]
    final public function __preUpdate( EventArgs $args ): void
    {
        $this->getCallbackClass( Events::preUpdate, $args )?->callback();
    }

    #[ORM\PostRemove]
    final public function __postRemove( EventArgs $args ): void
    {
        static::clearQueryBuilderCache();

        $this->getCallbackClass( Events::postRemove, $args )?->callback();
    }

    #[ORM\PostPersist]
    final public function __postPersist( EventArgs $args ): void
    {
        static::clearQueryBuilderCache();

        $this->getCallbackClass( Events::postPersist, $args )?->callback();
    }

    #[ORM\PostLoad]
    final public function __postLoad( EventArgs $args ): void
    {
        $this->getCallbackClass( Events::postLoad, $args )?->callback();
    }

    #[ORM\PostUpdate]
    final public function __postUpdate( EventArgs $args ): void
    {
        static::clearQueryBuilderCache();

        $this->getCallbackClass( Events::postUpdate, $args )?->callback();
    }

}
