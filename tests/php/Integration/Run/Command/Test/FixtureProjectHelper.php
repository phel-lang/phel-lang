<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test;

use RuntimeException;

use function dirname;
use function escapeshellarg;
use function exec;
use function implode;
use function is_resource;
use function sprintf;

/**
 * Materializes a fixture Phel project in a temp directory and runs
 * `phel test` against it as a subprocess.
 *
 * The copy is required because `phel test` skips every file under
 * `tests/php/Integration/` (the repo's own PHPUnit fixtures); a fixture
 * project living there would silently lose all its tests.
 */
final readonly class FixtureProjectHelper
{
    private function __construct(
        private string $projectDir,
        private string $repoRoot,
    ) {}

    /**
     * @param string $fixtureDir Directory containing the committed `.phel` fixtures (copied recursively, except PHP files)
     */
    public static function setUpProject(string $fixtureDir): self
    {
        $repoRoot = dirname(__DIR__, 6);
        $projectDir = sys_get_temp_dir() . '/phel-fixture-project-' . bin2hex(random_bytes(8));

        if (!mkdir($projectDir, 0755, true)) {
            throw new RuntimeException('Cannot create temp project dir: ' . $projectDir);
        }

        self::copyPhelFiles($fixtureDir, $projectDir);

        // Mark the temp dir as a project root for bin/phel's upward
        // vendor/autoload.php search, delegating to the real autoloader.
        mkdir($projectDir . '/vendor', 0755, true);
        file_put_contents(
            $projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $repoRoot),
        );

        file_put_contents(
            $projectDir . '/phel-config.php',
            sprintf(
                <<<'PHP'
                <?php

                declare(strict_types=1);

                use Phel\Config\PhelConfig;

                return new PhelConfig()
                    ->withSrcDirs(['%s/src/phel'])
                    ->withTestDirs(['Fixtures'])
                    ->withVendorDir('');

                PHP,
                $repoRoot,
            ),
        );

        return new self($projectDir, $repoRoot);
    }

    public function tearDownProject(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string} exit code and combined output
     */
    public function runPhelTest(array $arguments): array
    {
        $args = '';
        foreach ($arguments as $argument) {
            $args .= ' ' . escapeshellarg($argument);
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && php -d memory_limit=256M ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' test' . $args . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }

    /**
     * Runs `phel test --watch`, waits for the first idle announcement,
     * touches `$relativeFileToTouch` (with a future mtime so the second-level
     * granularity scanner is guaranteed to see it), waits for the watcher to
     * go idle again, then terminates the process. Returns everything written
     * to stdout/stderr.
     */
    public function runPhelTestWatchCycle(string $relativeFileToTouch, int $timeoutSeconds = 60): string
    {
        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && exec php -d memory_limit=256M ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' test --watch 2>&1';

        $process = proc_open($cmd, [1 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start phel test --watch');
        }

        stream_set_blocking($pipes[1], false);

        $output = '';
        $touched = false;
        $deadline = microtime(true) + $timeoutSeconds;
        $idleMarker = 'Watching for file changes';

        while (microtime(true) < $deadline) {
            $chunk = stream_get_contents($pipes[1]);
            if ($chunk !== false && $chunk !== '') {
                $output .= $chunk;
            }

            $idleCount = substr_count($output, $idleMarker);
            if (!$touched && $idleCount >= 1) {
                touch($this->projectDir . '/' . $relativeFileToTouch, time() + 2);
                $touched = true;
            }

            if ($touched && $idleCount >= 2) {
                break;
            }

            usleep(100_000);
        }

        proc_terminate($process, 9);
        fclose($pipes[1]);
        proc_close($process);

        return $output;
    }

    private static function copyPhelFiles(string $from, string $to): void
    {
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            if ($entry === '.phel') {
                continue;
            }

            $source = $from . '/' . $entry;
            $target = $to . '/' . $entry;

            if (is_dir($source)) {
                mkdir($target, 0755, true);
                self::copyPhelFiles($source, $target);
            } elseif (str_ends_with($entry, '.phel')) {
                copy($source, $target);
            }
        }
    }
}
