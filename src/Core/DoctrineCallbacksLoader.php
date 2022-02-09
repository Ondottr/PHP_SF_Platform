<?php /** @noinspection MagicMethodsValidityInspection */
declare( strict_types=1 );

namespace PHP_SF\System\Core;

use Doctrine\ORM\Events;
use Doctrine\Common\EventArgs;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Interface\DoctrineCallbacksLoaderInterface;
use PHP_SF\System\Classes\Abstracts\AbstractDoctrineLifecycleCallback;
use function in_array;
use function array_key_exists;


/**
 * @ORM\HasLifecycleCallbacks
 */
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

    /**
     * @ORM\PreFlush
     */
    final public function __preFlush(EventArgs $args): void
    {
        $this->getCallbackClass( Events::preFlush, $args )
             ?->callback();
    }

    final public function getCallbackClass(string $callback, EventArgs $args): ?AbstractDoctrineLifecycleCallback
    {
        return !in_array( $callback, self::AVAILABLE_CALLBACKS, true ) ||
               !array_key_exists( $callback, $this->getLifecycleCallbacks() ) ? null
            : new ( $this->getLifecycleCallbacks()[ $callback ] )( $this, $args );
    }

    /**
     * @ORM\PreRemove
     */
    final public function __preRemove(EventArgs $args): void
    {
        $this->getCallbackClass( Events::preRemove, $args )
             ?->callback();
    }

    /**
     * @ORM\PrePersist
     */
    final public function __prePersist(EventArgs $args): void
    {
        $this->getCallbackClass( Events::prePersist, $args )
             ?->callback();
    }

    /**
     * @ORM\PreUpdate
     */
    final public function __preUpdate(EventArgs $args): void
    {
        $this->getCallbackClass( Events::preUpdate, $args )
             ?->callback();
    }

    /**
     * @ORM\PostRemove
     */
    final public function __postRemove(EventArgs $args): void
    {
        static::clearRepositoryCache();
        static::clearQueryBuilderCache();

        $this->getCallbackClass( Events::postRemove, $args )
             ?->callback();
    }

    /**
     * @ORM\PostPersist
     */
    final public function __postPersist(EventArgs $args): void
    {
        static::clearRepositoryCache();
        static::clearQueryBuilderCache();

        $this->getCallbackClass( Events::postPersist, $args )
             ?->callback();
    }

    /**
     * @ORM\PostLoad
     */
    final public function __postLoad(EventArgs $args): void
    {
        $this->getCallbackClass( Events::postLoad, $args )
             ?->callback();
    }

    /**
     * @ORM\PostUpdate
     */
    final public function __postUpdate(EventArgs $args): void
    {
        static::clearRepositoryCache();
        static::clearQueryBuilderCache();

        $this->getCallbackClass( Events::postUpdate, $args )
             ?->callback();
    }

}
