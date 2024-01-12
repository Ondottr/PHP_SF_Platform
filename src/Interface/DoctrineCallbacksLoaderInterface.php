<?php /** @noinspection PhpAttributeCanBeAddedToOverriddenMemberInspection */
declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
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


namespace PHP_SF\System\Interface;

use Doctrine\Common\EventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;


/**
 * @ORM\HasLifecycleCallbacks
 */
interface DoctrineCallbacksLoaderInterface
{

    #[ArrayShape( [
        Events::postRemove  => 'string',
        Events::postUpdate  => 'string',
        Events::postPersist => 'string',
        Events::preRemove   => 'string',
        Events::postLoad    => 'string',
        Events::preFlush    => 'string',
        Events::prePersist  => 'string',
        Events::preUpdate   => 'string',
    ] )]
    public function getLifecycleCallbacks(): array;


    /**
     * @ORM\PreFlush
     *
     * The {@see preFlush} event occurs at the very beginning of a flush operation.
     */
    public function __preFlush( EventArgs $args ): void;

    /**
     * @ORM\PreRemove
     *
     * The {@see PreRemove} event occurs for a given entity before the respective EntityManager remove operation for
     * that entity is executed. It is not called for a DQL DELETE statement.
     */
    public function __preRemove( EventArgs $args ): void;

    /**
     * @ORM\PrePersist
     *
     * The {@see PrePersist} event occurs for a given entity before the respective EntityManager persist operation for
     * that entity is executed. It should be noted that this event is only triggered on initial persist of an entity
     * (i.e. it does not trigger on future updates).
     */
    public function __prePersist( EventArgs $args ): void;

    /**
     * @ORM\PreUpdate
     *
     * The {@see PreUpdate} event occurs before the database update operations to entity data.
     * It is not called for a DQL UPDATE statement nor when the computed change-set is empty.
     */
    public function __preUpdate( EventArgs $args ): void;

    /**
     * @ORM\PostRemove
     *
     * The {@see PostRemove} event occurs for an entity after the entity has been deleted.
     * It will be invoked after the database delete operations. It is not called for a DQL DELETE statement.
     */
    public function __postRemove( EventArgs $args ): void;

    /**
     * @ORM\PostPersist
     *
     * The {@see PostPersist} event occurs for an entity after the entity has been made persistent.
     * It will be invoked after the database insert operations. Generated primary key values are available in the
     * postPersist event.
     */
    public function __postPersist( EventArgs $args ): void;

    /**
     * @ORM\PostLoad
     *
     * The {@see PostLoad} event occurs for an entity after the entity has been loaded into the current EntityManager
     * from the database or after the refresh operation has been applied to it.
     */
    public function __postLoad( EventArgs $args ): void;

    /**
     * @ORM\PostUpdate
     *
     * The {@see PostUpdate} event occurs after the database update operations to entity data.
     * It is not called for a DQL UPDATE statement.
     */
    public function __postUpdate( EventArgs $args ): void;

}
