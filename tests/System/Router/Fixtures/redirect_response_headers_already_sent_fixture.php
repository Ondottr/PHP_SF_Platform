<?php declare(strict_types=1);

/*
 * Subprocess fixture for RedirectResponseSendHeadersAlreadySentTest.
 *
 * Reproduces the exact bug pattern that #56 / NO-32 describe:
 *   1. A non-PHP_SF Response is dispatched by Router::sendRouteMethodResponse()
 *      (so it falls into the `else` branch at Platform/src/Router.php:727).
 *   2. The response echoes a literal `<script>` payload directly to the SAPI
 *      before calling sendHeaders(), exactly the way PHP_SF\System\Core\RedirectResponse::send()
 *      does at lines 92-96.
 *   3. With output_buffering=0 the literal echo flushes the SAPI buffer
 *      immediately — the subsequent sendHeaders() call emits the
 *      "headers already sent" warning.
 *
 * The subprocess captures every emitted warning on STDERR. The PHPUnit test
 * spawns this script and asserts that no "headers already sent" line appears.
 *
 * Run requirements:
 *   - vendor/autoload.php at the template root (resolved relative to this file)
 *   - PHP CLI invoked with `-d output_buffering=0`
 */

namespace PHP_SF\Tests\System\Router\Fixtures;

use PHP_SF\System\Core\PhpSfEventDispatcher;
use PHP_SF\System\Router;
use ReflectionClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

// ---------------------------------------------------------------------------
// Resolve the template root from this file's location and bootstrap autoload.
// Fixture lives at: Platform/tests/System/Router/Fixtures/redirect_response_…php
// Going up five levels lands at the template root, where vendor/autoload.php lives.
// ---------------------------------------------------------------------------
$autoload = __DIR__ . '/../../../../../vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "FIXTURE-SETUP-FAIL: vendor/autoload.php not found at $autoload\n");
    exit(2);
}

require_once $autoload;

// ---------------------------------------------------------------------------
// Force-load the FIXTURE-TESTED framework source from Platform/src, not the
// Packagist-installed copy at vendor/nations-original/php-simple-framework/.
// setPsr4() replaces the existing prefix mapping outright, so Platform/src
// wins over the Packagist copy at vendor/ for every PHP_SF\System\* class.
// ---------------------------------------------------------------------------
$composerLoader = require $autoload;

$composerLoader->setPsr4('PHP_SF\\System\\', [__DIR__ . '/../../../../src']);
$composerLoader->setPsr4('PHP_SF\\Framework\\', [__DIR__ . '/../../../../app']);
$composerLoader->setPsr4('PHP_SF\\Templates\\', [__DIR__ . '/../../../../templates']);

// ---------------------------------------------------------------------------
// Composer's classmap is generated at install time and points Router at the
// Packagist copy. setPsr4 alone doesn't help — the classmap is checked first.
// Eagerly require Platform/src/Router.php so PHP loads the FIXTURE-TESTED
// source (where the #56 fix lives) directly.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../../../../src/Router.php';

// Sanity check — Platform/src/Router.php must exist and be the source of truth.
$platformRouter = __DIR__ . '/../../../../src/Router.php';
if (!is_file($platformRouter)) {
    fwrite(STDERR, "FIXTURE-SETUP-FAIL: Platform/src/Router.php not found at $platformRouter\n");
    exit(2);
}

// ---------------------------------------------------------------------------
// Minimal framework constants and helpers that sendRouteMethodResponse() and
// PhpSfEventDispatcher::dispatch() rely on. We never run Router::init() (too
// heavy), so we only need the surface that the dispatcher touches:
//   - DEV_MODE = true  (skip the cache lookup of pre-discovered subscribers)
//   - TEMPLATES_CACHE_ENABLED = false  (so the ob_start closure returns the
//     raw buffer; the closure is part of Router::sendRouteMethodResponse and
//     must be readable when the dispatcher runs)
//   - ca()  (cache accessor; the dispatcher uses it when DEV_MODE is false —
//     we keep DEV_MODE true so ca() is not touched, but we still provide a
//     stub for safety in case any listener probes it)
// ---------------------------------------------------------------------------
if (!defined('DEV_MODE')) {
    define('DEV_MODE', true);
}
if (!defined('TEMPLATES_CACHE_ENABLED')) {
    define('TEMPLATES_CACHE_ENABLED', false);
}

if (!function_exists('ca')) {
    require_once __DIR__ . '/../../../../../vendor/autoload.php'; // redundant safety net
}

// ---------------------------------------------------------------------------
// Custom Symfony stock RedirectResponse that emits a literal <script> before
// delegating to parent::send(). This mirrors the pattern in
// PHP_SF\System\Core\RedirectResponse at lines 92-99, but extends Symfony's
// stock RedirectResponse so it falls into the `else` branch of
// Router::sendRouteMethodResponse() (Symfony stock RedirectResponse is NOT
// instanceof PHP_SF\System\Core\Response).
// ---------------------------------------------------------------------------
final class ScriptEmittingRedirectResponse extends RedirectResponse
{
    public function send(bool $flush = true): static
    {
        // Mirror PHP_SF\System\Core\RedirectResponse::send() lines 92-99: the
        // literal <script> echo lands on the SAPI before sendHeaders() runs.
        // Under output_buffering=0 this is what flips `headers_sent()` to true.
        echo "<script>history.replaceState({}, '', '/home');</script>";

        // Direct header() call — mirrors the warning's real call site
        // (vendor/symfony/http-foundation/Response.php:328 — but Symfony
        // guards that path with `PHP_SAPI !== 'cli'`, which would silently
        // no-op under the PHPUnit harness). Calling header() directly here
        // reproduces the warning under any SAPI.
        header('X-PHP-SF-Redirect-Test: 1');

        return parent::send($flush);
    }
}

// ---------------------------------------------------------------------------
// Capture every emitted warning / notice on STDERR so the parent PHPUnit test
// can inspect them. We echo the captured text immediately (rather than only at
// script end) because Router::sendRouteMethodResponse() ends with `exit;`,
// which skips any post-dispatch cleanup.
// ---------------------------------------------------------------------------
// Suppress E_DEPRECATED / E_USER_DEPRECATED — the deprecation noise from
// ReflectionProperty::setAccessible() (no-op since PHP 8.1) would otherwise
// dominate STDERR and obscure the warning we are actually looking for.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
$stderrCapture = '';
set_error_handler(static function (int $errno, string $errstr, string $errfile = '', int $errline = 0) use (&$stderrCapture): bool {
    // PHP 8.5 raises E_DEPRECATED for ReflectionProperty::setAccessible() and
    // friends (no-op since 8.1). Filter those — they drown out the actual
    // warning we are looking for.
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
        return true;
    }
    $line = sprintf("[errno=%d] %s at %s:%d\n", $errno, $errstr, $errfile, $errline);
    $stderrCapture .= $line;
    fwrite(STDERR, $line);
    return true;
});

// ---------------------------------------------------------------------------
// Drive Router::sendRouteMethodResponse() through reflection on the static
// fields the method reads: $routeMethodResponse, $kernel, $requestData.
// ---------------------------------------------------------------------------
$routerReflection = new ReflectionClass(Router::class);

foreach ([
    'routeMethodResponse' => new ScriptEmittingRedirectResponse('/home'),
    'kernel' => new \PHP_SF\System\Kernel(),
    'requestData' => Request::create('/home'),
] as $property => $value) {
    $prop = $routerReflection->getProperty($property);
    $prop->setAccessible(true);
    $prop->setValue(null, $value);
}

// ---------------------------------------------------------------------------
// Force the dispatcher into initialized state with no subscribers, so the
// KernelEvents::RESPONSE dispatch is a no-op. sendRouteMethodResponse()
// dispatches that event before the if/else block — we want a clean dispatch
// so the assertion focuses on the wrap, not on subscriber side effects.
// ---------------------------------------------------------------------------
$dispatcherReflection = new ReflectionClass(PhpSfEventDispatcher::class);
$initializedProp = $dispatcherReflection->getProperty('initialized');
$initializedProp->setAccessible(true);
$initializedProp->setValue(null, true);

$dispatcherInstanceProp = $dispatcherReflection->getProperty('dispatcher');
$dispatcherInstanceProp->setAccessible(true);
$dispatcherInstanceProp->setValue(null, new \Symfony\Component\EventDispatcher\EventDispatcher());

// ---------------------------------------------------------------------------
// Invoke the dispatcher path. sendRouteMethodResponse() is `never`-returning:
// it ends with `exit;` so this script terminates cleanly. If the wrap is
// missing, the raw $response->send() call above will fire
// "Cannot modify header information - headers already sent" on STDERR.
// ---------------------------------------------------------------------------
try {
    $sendRouteMethodResponse = $routerReflection->getMethod('sendRouteMethodResponse');
    $sendRouteMethodResponse->setAccessible(true);
    $sendRouteMethodResponse->invoke(null);
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf("UNEXPECTED-EXCEPTION: %s: %s at %s:%d\n",
        $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    exit(3);
}

// Reached only if the dispatcher exits cleanly without our catch firing.
restore_error_handler();
fwrite(STDERR, $stderrCapture);
exit(0);
