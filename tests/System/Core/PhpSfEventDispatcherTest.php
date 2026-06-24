<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Core;

use FilesystemIterator;
use PHP_SF\System\Core\PhpSfEventDispatcher;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use stdClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// ---------------------------------------------------------------------------
// Inline subscriber fixture — defined here so is_a() recognises it without
// autoloading when the dispatcher reads a file that declares this FQCN.
// ---------------------------------------------------------------------------

final class DispatcherTestSubscriber implements EventSubscriberInterface
{
    public static int $calls = 0;


    public function onTestEvent(): void
    {
        ++self::$calls;
    }

    public static function getSubscribedEvents(): array
    {
        return ['phpsf.test_event' => 'onTestEvent'];
    }
}

final class DispatcherTestNonSubscriber
{
    // intentionally does NOT implement EventSubscriberInterface
}

final class PhpSfEventDispatcherTest extends TestCase
{
    private string $tmpDir;
    private array $savedState;


    public function testDispatchWithNoDirectoriesDoesNotThrow(): void
    {
        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());
        $this->assertSame(0, DispatcherTestSubscriber::$calls);
    }

    public function testNonExistentDirectoryIsIgnored(): void
    {
        PhpSfEventDispatcher::addSubscriberDirectory('/nonexistent/path/that/does/not/exist');
        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());

        $this->assertSame(0, DispatcherTestSubscriber::$calls);
    }

    public function testNonPhpFileIsIgnored(): void
    {
        file_put_contents($this->tmpDir . '/readme.txt', 'not PHP');
        PhpSfEventDispatcher::addSubscriberDirectory($this->tmpDir);
        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());

        $this->assertSame(0, DispatcherTestSubscriber::$calls);
    }

    public function testPhpFileWithoutNamespaceIsIgnored(): void
    {
        file_put_contents($this->tmpDir . '/NoNamespace.php', "<?php\nclass NoNamespace {}");
        PhpSfEventDispatcher::addSubscriberDirectory($this->tmpDir);
        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());

        $this->assertSame(0, DispatcherTestSubscriber::$calls);
    }

    public function testNonSubscriberClassIsIgnored(): void
    {
        // File declares DispatcherTestNonSubscriber — already loaded, is_a() returns false.
        $this->writeClassFile(DispatcherTestNonSubscriber::class);
        PhpSfEventDispatcher::addSubscriberDirectory($this->tmpDir);
        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());

        $this->assertSame(0, DispatcherTestSubscriber::$calls);
    }

    public function testSubscriberInDirectoryReceivesDispatchedEvent(): void
    {
        // File declares DispatcherTestSubscriber — already loaded, is_a() returns true.
        $this->writeClassFile(DispatcherTestSubscriber::class);
        PhpSfEventDispatcher::addSubscriberDirectory($this->tmpDir);
        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());

        $this->assertSame(1, DispatcherTestSubscriber::$calls);
    }

    public function testSubscriberCalledOncePerDispatch(): void
    {
        $this->writeClassFile(DispatcherTestSubscriber::class);
        PhpSfEventDispatcher::addSubscriberDirectory($this->tmpDir);

        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());
        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());

        $this->assertSame(2, DispatcherTestSubscriber::$calls);
    }

    public function testUnrelatedEventDoesNotTriggerSubscriber(): void
    {
        $this->writeClassFile(DispatcherTestSubscriber::class);
        PhpSfEventDispatcher::addSubscriberDirectory($this->tmpDir);

        PhpSfEventDispatcher::dispatch('phpsf.different_event', new stdClass());

        $this->assertSame(0, DispatcherTestSubscriber::$calls);
    }

    public function testDuplicateFilesDeduplicatedByFqcn(): void
    {
        $subDir = $this->tmpDir . '/sub';
        mkdir($subDir, 0777, true);

        // Same FQCN in two files — must only register the subscriber once.
        $this->writeClassFile(DispatcherTestSubscriber::class);
        $this->writeClassFile(DispatcherTestSubscriber::class, $subDir . '/Duplicate.php');

        PhpSfEventDispatcher::addSubscriberDirectory($this->tmpDir);
        PhpSfEventDispatcher::dispatch('phpsf.test_event', new stdClass());

        $this->assertSame(1, DispatcherTestSubscriber::$calls);
    }

    protected function setUp(): void
    {
        $this->savedState = $this->captureState();
        $this->resetDispatcher([], false, null);

        $this->tmpDir = sys_get_temp_dir() . '/phpsf_dispatcher_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);

        DispatcherTestSubscriber::$calls = 0;
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
        $this->restoreState($this->savedState);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function writeClassFile(string $fqcn, ?string $path = null): void
    {
        $lastBackslash = strrpos($fqcn, '\\');
        $namespace = substr($fqcn, 0, $lastBackslash);
        $className = substr($fqcn, $lastBackslash + 1);

        $content = "<?php\nnamespace {$namespace};\nfinal class {$className} {}";
        file_put_contents($path ?? ($this->tmpDir . '/' . $className . '.php'), $content);
    }

    private function captureState(): array
    {
        $ref = new ReflectionClass(PhpSfEventDispatcher::class);

        return [
            'dirs' => $ref->getProperty('subscriberDirs')->getValue(null),
            'initialized' => $ref->getProperty('initialized')->getValue(null),
            'dispatcher' => $ref->getProperty('dispatcher')->getValue(null),
        ];
    }

    private function restoreState(array $state): void
    {
        $ref = new ReflectionClass(PhpSfEventDispatcher::class);
        $ref->getProperty('subscriberDirs')->setValue(null, $state['dirs']);
        $ref->getProperty('initialized')->setValue(null, $state['initialized']);
        $ref->getProperty('dispatcher')->setValue(null, $state['dispatcher']);
    }

    private function resetDispatcher(array $dirs, bool $initialized, mixed $dispatcher): void
    {
        $ref = new ReflectionClass(PhpSfEventDispatcher::class);
        $ref->getProperty('subscriberDirs')->setValue(null, $dirs);
        $ref->getProperty('initialized')->setValue(null, $initialized);
        $ref->getProperty('dispatcher')->setValue(null, $dispatcher);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
