<?php
declare(strict_types=1);

namespace PHP_SF\System\Core;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PhpSfEventDispatcher
{

    private static ?EventDispatcher $dispatcher     = null;
    private static array            $subscriberDirs = [];
    private static bool             $initialized    = false;


    public static function addSubscriberDirectory(string $dir): void
    {
        self::$subscriberDirs[] = $dir;
        self::$initialized      = false;
    }

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$dispatcher  = new EventDispatcher();
        self::$initialized = true;

        $classes = DEV_MODE === false
            ? json_decode((string)ca()->get('phpsf:event_subscribers'), true)
            : null;

        if ($classes === null) {
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
        if ( !self::$initialized) {
            self::initialize();
        }

        return self::$dispatcher->dispatch($event, $eventName);
    }


    private static function discoverSubscriberClasses(): array
    {
        $found = [];

        foreach (self::$subscriberDirs as $dir) {
            if ( !is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                if (preg_match('/namespace\s+([\w\\\\]+)\s*;/', $contents, $ns)
                    && preg_match('/(?:class|final\s+class)\s+(\w+)/', $contents, $cls)
                ) {
                    $fqcn = $ns[1].'\\'.$cls[1];

                    if ( !isset($found[$fqcn]) && is_a($fqcn, EventSubscriberInterface::class, true)) {
                        $found[$fqcn] = $fqcn;
                    }
                }
            }
        }

        return array_values($found);
    }

}
