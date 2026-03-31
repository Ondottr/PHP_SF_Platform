<?php /** @noinspection MagicMethodsValidityInspection */

declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use Doctrine\Common\EventArgs;

abstract class AbstractDoctrineLifecycleCallback
{
    public function __construct(
        protected AbstractEntity $entity,
        protected EventArgs      $args
    ) {}

    abstract public function callback(): void;
}
