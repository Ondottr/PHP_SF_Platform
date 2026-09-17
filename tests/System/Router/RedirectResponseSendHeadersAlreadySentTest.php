<?php declare(strict_types=1);

namespace PHP_SF\Tests\System\Router;

use PHPUnit\Framework\TestCase;

/**
 * Regression test for Ondottr/PHP_SF_Platform#56 / YouTrack NO-32.
 *
 * The dispatcher at Platform/src/Router.php::sendRouteMethodResponse() used to
 * call $response->send() raw in its `else` branch (lines 727-729 pre-fix).
 * When the response is a non-PHP_SF\Core\Response (e.g. Symfony stock
 * RedirectResponse) that emits output to the SAPI before sendHeaders() — as
 * PHP_SF\System\Core\RedirectResponse::send() does at lines 92-99 — the
 * post-output `header()` call raises
 *
 *     Warning: Cannot modify header information - headers already sent
 *
 * under the default `output_buffering = 0` of php:8-fpm / php:8.3-cli.
 *
 * This test spawns a fixture subprocess (Fixtures/redirect_response_headers_already_sent_fixture.php)
 * with `output_buffering=0` forced on the CLI. The fixture drives
 * Router::sendRouteMethodResponse() with a custom Symfony stock RedirectResponse
 * that mirrors PHP_SF\System\Core\RedirectResponse::send()'s pre-send echo.
 *
 *   - Pre-fix: the else-branch calls $response->send() raw → warning fires →
 *     the test FAILS because stderr contains "headers already sent".
 *   - Post-fix: the else-branch wraps the send in ob_start() + try/catch
 *     (mirroring the if-branch) → the <script> echo lands in the buffer,
 *     sendHeaders() succeeds, no warning → the test PASSES.
 */
final class RedirectResponseSendHeadersAlreadySentTest extends TestCase
{
    private const FIXTURE_SCRIPT = __DIR__ . '/Fixtures/redirect_response_headers_already_sent_fixture.php';

    public function testElseBranchDoesNotEmitHeadersAlreadySentWarningWhenOutputBufferingIsZero(): void
    {
        $this->assertFileExists(self::FIXTURE_SCRIPT, 'Fixture script must exist for the subprocess to run');

        $phpBinary = PHP_BINARY;
        $fixtureAbs = realpath(self::FIXTURE_SCRIPT);
        $this->assertNotFalse($fixtureAbs, 'Fixture path must be resolvable');

        $command = [
            $phpBinary,
            '-d', 'output_buffering=0',
            '-d', 'implicit_flush=1',
            '-d', 'display_errors=1',
            '-d', 'error_reporting=E_ALL',
            '-d', 'log_errors=0',
            $fixtureAbs,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        $this->assertIsResource($process, 'Failed to launch fixture subprocess — proc_open returned false');

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        // Exit code 2 = fixture setup failure (autoload not found).
        // Exit code 3 = unexpected exception escaped the dispatcher.
        // Anything else = the fixture either exited cleanly (0, no warning) or
        // exited with an unexpected code from PHP itself.
        $this->assertNotSame(
            2,
            $exitCode,
            "Fixture setup failed (autoload missing?). STDOUT:\n{$stdout}\nSTDERR:\n{$stderr}",
        );
        $this->assertNotSame(
            3,
            $exitCode,
            "Fixture raised an unexpected exception. STDOUT:\n{$stdout}\nSTDERR:\n{$stderr}",
        );

        // The substantive assertion: no "headers already sent" warning in STDERR.
        $this->assertStringNotContainsString(
            'headers already sent',
            $stderr,
            "Router::sendRouteMethodResponse()'s else branch emitted a headers-already-sent warning under output_buffering=0. "
            . "This is the bug from #56 / NO-32 — the else branch must wrap \$response->send() in ob_start() + try/catch. "
            . "STDERR was:\n{$stderr}",
        );

        // Also fail loudly if any warning was emitted, even if not the
        // headers-already-sent one — that would indicate a regression in the
        // dispatcher's wrap contract.
        $this->assertStringNotContainsString(
            'Warning:',
            $stderr,
            "Subprocess emitted a Warning on STDERR:\n{$stderr}",
        );
        $this->assertStringNotContainsString(
            'Notice:',
            $stderr,
            "Subprocess emitted a Notice on STDERR:\n{$stderr}",
        );
    }
}
