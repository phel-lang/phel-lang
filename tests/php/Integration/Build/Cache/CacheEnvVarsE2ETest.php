<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Cache;

use Override;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * The compiled-code cache is keyed by source content, so a macro that reads
 * `php/getenv` baked the value of its first compile into the emitted PHP and
 * every later run with another value was served that expansion (#3236).
 * `cache-env-vars` names the variables that take part in the key.
 */
final class CacheEnvVarsE2ETest extends TestCase
{
    private string $projectDir;

    private string $repoRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 5);
        $this->projectDir = sys_get_temp_dir() . '/phel-cache-env-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/src', 0o755, true);
        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );
        file_put_contents(
            $this->projectDir . '/src/main.phel',
            "(ns app.main)\n\n"
            . "(defmacro current-mode []\n  (php/getenv \"MY_MODE\"))\n\n"
            . "(println (str \"mode=\" (current-mode)))\n",
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_a_declared_env_var_recompiles_the_macro_expansion(): void
    {
        $this->writeConfig(declaredEnvVars: ['MY_MODE']);

        [$exitCode, $first] = $this->runMain('MY_MODE=alpha');
        self::assertSame(0, $exitCode, $first);
        self::assertStringContainsString('mode=alpha', $first);

        [$exitCode, $second] = $this->runMain('MY_MODE=beta');
        self::assertSame(0, $exitCode, $second);
        self::assertStringContainsString('mode=beta', $second, 'the warm cache must not serve the alpha expansion');
    }

    public function test_an_undeclared_env_var_keeps_serving_the_cached_expansion(): void
    {
        // The escape hatch is opt-in: without the declaration the cache still
        // knows nothing about the variable, which is what #3236 reported.
        $this->writeConfig(declaredEnvVars: []);

        [$exitCode, $first] = $this->runMain('MY_MODE=alpha');
        self::assertSame(0, $exitCode, $first);
        self::assertStringContainsString('mode=alpha', $first);

        [$exitCode, $second] = $this->runMain('MY_MODE=beta');
        self::assertSame(0, $exitCode, $second);
        self::assertStringContainsString('mode=alpha', $second, 'undeclared: the first expansion is reused');
    }

    public function test_a_declared_env_var_recompiles_the_build_output(): void
    {
        $this->writeConfig(declaredEnvVars: ['MY_MODE']);

        [$exitCode, $first] = $this->runBuild('MY_MODE=alpha');
        self::assertSame(0, $exitCode, $first);
        self::assertStringContainsString('mode=alpha', $this->builtOutput());

        // `phel build` reuses an output file by mtime alone, so the flip has to
        // be recorded next to the artifact or the build ships the old expansion.
        [$exitCode, $second] = $this->runBuild('MY_MODE=beta');
        self::assertSame(0, $exitCode, $second);
        self::assertStringContainsString('mode=beta', $this->builtOutput());
    }

    /**
     * @param list<string> $declaredEnvVars
     */
    private function writeConfig(array $declaredEnvVars): void
    {
        $names = implode(', ', array_map(
            static fn(string $name): string => sprintf("'%s'", $name),
            $declaredEnvVars,
        ));

        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\nreturn new \\Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs(['src'])->withTestDirs([])->withVendorDir('')\n"
            . sprintf("    ->withCacheEnvVars([%s]);\n", $names),
        );
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runMain(string $env): array
    {
        return $this->runPhel($env, 'run src/main.phel');
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runBuild(string $env): array
    {
        return $this->runPhel($env, 'build');
    }

    private function builtOutput(): string
    {
        $file = $this->projectDir . '/out/app/main.php';
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runPhel(string $env, string $args): array
    {
        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && ' . $env . ' php ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' ' . $args . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
