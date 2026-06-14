<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Run;

use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function array_values;
use function bin2hex;
use function count;
use function dirname;
use function escapeshellarg;
use function exec;
use function explode;
use function file_put_contents;
use function implode;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * End-to-end coverage for `phel run` / `phel test` shell completion: spins up a
 * tiny fixture project with two known namespaces and drives the hidden
 * `_complete` command the way the generated shell script does.
 */
final class RunCommandCompletionTest extends TestCase
{
    private string $projectDir = '';

    private string $repoRoot = '';

    #[Override]
    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 6);
        $this->projectDir = sys_get_temp_dir() . '/phel-completion-project-' . bin2hex(random_bytes(8));

        mkdir($this->projectDir . '/src/app', 0755, true);
        mkdir($this->projectDir . '/vendor', 0755, true);

        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            <<<'PHP'
            <?php

            declare(strict_types=1);

            use Phel\Config\PhelConfig;

            return new PhelConfig()
                ->withSrcDirs(['src'])
                ->withTestDirs([])
                ->withVendorDir('');

            PHP,
        );
        file_put_contents($this->projectDir . '/src/app/main.phel', "(ns app\\main)\n");
        file_put_contents($this->projectDir . '/src/app/web.phel', "(ns app\\web)\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_run_path_argument_completes_project_namespaces(): void
    {
        $suggestions = $this->complete(['phel', 'run', 'app']);

        self::assertContains('app.main', $suggestions);
        self::assertContains('app.web', $suggestions);
    }

    public function test_test_ns_option_completes_project_namespaces(): void
    {
        $suggestions = $this->complete(['phel', 'test', '--ns=app']);

        self::assertContains('app.main', $suggestions);
        self::assertContains('app.web', $suggestions);
    }

    /**
     * Drives the Symfony `_complete` backend exactly as the generated zsh
     * script does: the current word index is `count - 1` and every word is
     * passed as a separate `-i` input.
     *
     * @param list<string> $words
     *
     * @return list<string>
     */
    private function complete(array $words): array
    {
        $current = count($words) - 1;

        $inputs = '';
        foreach ($words as $word) {
            $inputs .= ' -i' . escapeshellarg($word);
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && php -d memory_limit=256M ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' _complete --no-interaction -szsh -a1 -c' . $current . $inputs . ' 2>/dev/null';

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('phel _complete failed: ' . implode("\n", $output));
        }

        return array_values(array_filter(explode("\n", implode("\n", $output))));
    }
}
