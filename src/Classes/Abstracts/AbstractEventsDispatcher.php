<?php declare( strict_types=1 );

namespace PHP_SF\System\Classes\Abstracts;

use PHP_SF\System\Interface\EventSubscriberInterface;


abstract class AbstractEventsDispatcher
{

    /**
     * @var string[]
     */
    protected static array $eventListenersList = [];
    /**
     * @var array[]
     */
    private static array $dispatchedListeners = [];

    public function __construct(
        EventSubscriberInterface $eventSubscriber,
        mixed                    ...$args
    )
    {
        $args[] = $eventSubscriber;

        foreach ($this->getEventListeners() as $listener)
            if ($eventSubscriber->dispatchEvent(new $listener, $args) === true)
                $this->addDispatchedListener($eventSubscriber, $listener);

    }

    /**
     * @return string[]
     */
    protected function getEventListeners(): array
    {
        return static::$eventListenersList;
    }

    private function addDispatchedListener(EventSubscriberInterface $eventSubscriber, string $listener): void
    {
        self::$dispatchedListeners[ $eventSubscriber::class ][] = $listener;
    }

    final public static function addEventListeners(string ...$events): void
    {
        static::$eventListenersList = array_merge(static::$eventListenersList, $events);
    }

    public static function getDispatchedListeners(): array
    {
        return self::$dispatchedListeners;
    }

}