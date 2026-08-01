<?php

declare(strict_types=1);

namespace PhelTest\Integration\Bootstrap;

use PHPUnit\Framework\TestCase;

use function array_map;
use function bin2hex;
use function dirname;
use function escapeshellarg;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function is_dir;
use function mkdir;
use function proc_close;
use function proc_open;
use function random_bytes;
use function stream_get_contents;
use function sys_get_temp_dir;
use function trim;

use const PHP_BINARY;

/**
 * A deployed build reports an uncaught throwable against the `.phel` source it
 * came from, not the generated PHP (#2922).
 *
 * The project is really built and really run in a subprocess, because both
 * halves matter: the report is decoded from the source map `phel build` writes,
 * and the handler ends in `exit(255)`. What a deployment sees is a plain `php`
 * process with no Phel CLI in it, which is what this runs.
 */
final class ExceptionHandlerTest extends TestCase
{
    private static string $projectDir = '';

    public static function setUpBeforeClass(): void
    {
        $repoRoot = dirname(__DIR__, 4);
        self::$projectDir = sys_get_temp_dir() . '/phel-handler-' . bin2hex(random_bytes(6));
        mkdir(self::$projectDir . '/src', 0o777, true);

        file_put_contents(self::$projectDir . '/phel-config.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Phel\Config\PhelConfig;

            return new PhelConfig()->withSrcDirs(['src']);
            PHP);

        file_put_contents(self::$projectDir . '/src/main.phel', <<<'PHEL'
            (ns app.main)

            (defn- boom []
              (throw (php/new \RuntimeException "boom")))

            (when-not *build-mode* (boom))
            PHEL);

        // The entry point a deployment runs: autoload, the handler, the
        // compiled namespace. No Phel CLI involved.
        file_put_contents(self::$projectDir . '/entry.php', <<<PHP
            <?php declare(strict_types=1);
            require_once '{$repoRoot}/vendor/autoload.php';
            \\Phel\\Phel::setupRuntimeArgs(__FILE__, []);
            \\Phel\\Phel::installExceptionHandler(__DIR__);
            require_once __DIR__ . '/out/app/main.php';
            PHP);

        self::runProcess([escapeshellarg($repoRoot . '/bin/phel'), 'build']);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$projectDir)) {
            self::runProcess(['rm', '-rf', escapeshellarg(self::$projectDir)]);
        }
    }

    public function test_the_report_names_the_phel_source_and_exits_255(): void
    {
        [$output, $exitCode] = self::runProcess([
            escapeshellarg(PHP_BINARY), '-d', 'display_errors=1', '-d', 'log_errors=0',
            escapeshellarg(self::$projectDir . '/entry.php'),
        ]);

        self::assertSame(255, $exitCode, 'the exit code PHP uses for an uncaught exception');
        self::assertStringContainsString('RuntimeException: boom', $output);
        self::assertStringContainsString('main.phel', $output, 'the report names the Phel source');
        self::assertStringContainsString('app\\main\\boom', $output, 'and the Phel call form per frame');
    }

    public function test_the_response_body_stays_clean_when_display_errors_is_off(): void
    {
        $log = self::$projectDir . '/error.log';

        [$output, $exitCode] = self::runProcess([
            escapeshellarg(PHP_BINARY), '-d', 'display_errors=0', '-d', 'log_errors=1',
            '-d', 'error_log=' . escapeshellarg($log),
            escapeshellarg(self::$projectDir . '/entry.php'),
        ]);

        self::assertSame(255, $exitCode);
        self::assertSame('', trim($output), 'a production response body gets no stack trace');

        $logged = (string) file_get_contents($log);
        self::assertStringContainsString('main.phel', $logged, 'the report goes to the error log instead');
        self::assertStringNotContainsString("\033[", $logged, 'a log sink gets no ANSI escapes');
    }

    /**
     * @param list<string> $command
     *
     * @return array{string, int}
     */
    private static function runProcess(array $command): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open(implode(' ', $command), $descriptors, $pipes, self::$projectDir);
        if ($proc === false) {
            self::fail('proc_open failed for: ' . implode(' ', $command));
        }

        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        array_map(fclose(...), $pipes);

        return [$output, proc_close($proc)];
    }
}
