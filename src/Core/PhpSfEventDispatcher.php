<?php declare(strict_types=1);

namespace PHP_SF\System\Core;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PhpSfEventDispatcher
{
    private static ?EventDispatcher $dispatcher = null;
    /**
     * @var list<string>
     */
    private static array $subscriberDirs = [];
    private static bool $initialized = false;
    /**
     * @var list<array{event: string, subscribers: list<string>}>
     */
    private static array $dispatchLog = [];

    public static function addSubscriberDirectory(string $dir): void
    {
        if (self::$initialized) {
            throw new \LogicException('Cannot register subscriber directory after the dispatcher is initialized.');
        }

        self::$subscriberDirs[] = $dir;
    }

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$dispatcher = new EventDispatcher();
        self::$initialized = true;

        $classes = DEV_MODE === false
            ? json_decode((string) ca()->get('phpsf:event_subscribers'), true)
            : null;

        if (null === $classes) {
            $classes = self::discoverSubscriberClasses();

            if (DEV_MODE === false) {
                ca()->set('phpsf:event_subscribers', json_encode($classes), null);
            }
        }

        foreach ($classes as $class) {
            self::$dispatcher->addSubscriber(new $class());
        }
    }

    public static function dispatch(string $eventName, object $event): object
    {
        if (!self::$initialized) {
            self::initialize();
        }

        $result = self::$dispatcher->dispatch($event, $eventName);

        if (DEV_MODE) {
            self::$dispatchLog[] = [
                'event' => $eventName,
                'subscribers' => array_map(
                    static fn ($l): string => is_array($l) ? get_class($l[0]) . '::' . $l[1] : (is_object($l) ? get_class($l) : (is_string($l) ? $l : '')),
                    self::$dispatcher->getListeners($eventName),
                ),
            ];
        }

        return $result;
    }

    /**
     * @return list<array{event: string, subscribers: list<string>}>
     */
    public static function getDispatchLog(): array
    {
        return self::$dispatchLog;
    }

    /**
     * @return list<class-string>
     */
    private static function discoverSubscriberClasses(): array
    {
        $found = [];

        foreach (self::$subscriberDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ('php' !== $file->getExtension()) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (false === $contents || '' === $contents) {
                    continue;
                }

                if (preg_match('/namespace\s+([\w\\\\]+)\s*;/', $contents, $ns)
                    && preg_match('/(?:class|final\s+class)\s+(\w+)/', $contents, $cls)
                ) {
                    $fqcn = $ns[1] . '\\' . $cls[1];

                    if (!isset($found[$fqcn]) && is_a($fqcn, EventSubscriberInterface::class, true)) {
                        $found[$fqcn] = $fqcn;
                    }
                }
            }
        }

        return array_values($found);
    }
}
