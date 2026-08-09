<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Eval;

use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function implode;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;

/**
 * `phel eval` loads the files a `(:require ...)` resolves to, and used to do it
 * with build mode switched on, borrowing it as a recursion guard.
 *
 * Build mode also licenses the emitter to pin a global call site into a
 * `static $__phel_call_N` slot, which is sound only when redefinitions are not
 * expected. A runtime dependency load does not meet that: the pinned artifact
 * ignored every later `with-redefs`, and it was written to the ordinary cache
 * for the next process to pick up, so `phel test` failed afterwards until the
 * cache was deleted by hand (#3015).
 */
final class EvalDependencyRedefTest extends TestCase
{
    private string $projectDir = '';

    protected function setUp(): void
    {
        $repoRoot = dirname(__DIR__, 6);
        $this->projectDir = sys_get_temp_dir() . '/phel-eval-redef-' . bin2hex(random_bytes(8));

        mkdir($this->projectDir . '/src/app', 0755, true);
        mkdir($this->projectDir . '/vendor', 0755, true);

        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $repoRoot),
        );

        file_put_contents($this->projectDir . '/phel-config.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            use Phel\Config\PhelConfig;

            $cacheDir = __DIR__ . '/.phel-cache';

            return new PhelConfig()
                ->withSrcDirs(['src'])
                ->withTestDirs(['tests'])
                ->withVendorDir('vendor')
                ->withPhelDir($cacheDir)
                ->withTempDir($cacheDir . '/tmp');

            PHP);

        file_put_contents($this->projectDir . '/src/app/target.phel', <<<'PHEL'
            (ns app.target)

            (defn read-search []
              "real-value")

            PHEL);

        // The indirection matters: `runner` is the namespace whose compiled
        // artifact holds the call site, and it is reached only as a dependency.
        file_put_contents($this->projectDir . '/src/app/runner.phel', <<<'PHEL'
            (ns app.runner
              (:require app.target :as target))

            (defn search-filter []
              {:search (target/read-search)})

            PHEL);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_a_second_with_redefs_is_visible_through_a_required_namespace(): void
    {
        [$exitCode, $output] = $this->runEval();

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString('{:search first}', $output);
        self::assertStringContainsString(
            '{:search second}',
            $output,
            'the second redefinition was ignored, so the call site is pinned',
        );
    }

    /**
     * The artifact outlives the process that wrote it, so a run that looks
     * correct can still leave a poisoned cache behind for the next one.
     */
    public function test_the_cached_artifact_does_not_pin_the_call_site(): void
    {
        $this->runEval();

        $compiled = glob($this->projectDir . '/.phel-cache/cache/compiled/app.runner__*.php');
        self::assertNotSame([], $compiled, 'expected a compiled artifact for app.runner');

        $contents = (string) file_get_contents($compiled[0]);
        self::assertStringNotContainsString(
            '$__phel_call_',
            $contents,
            'the dependency was compiled as if for a build, pinning its call sites',
        );
        self::assertStringContainsString(
            'getDefinition("app.target", "read-search")', $contents,
            'the call site should still resolve through the registry',
        );
    }

    public function test_a_later_process_reusing_the_cache_still_sees_the_redefinition(): void
    {
        $this->runEval();

        [$exitCode, $output] = $this->runEval();

        self::assertSame(0, $exitCode, $output);
        self::assertStringContainsString(
            '{:search second}',
            $output,
            'a warm cache reintroduced the pinned call site',
        );
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runEval(): array
    {
        $source = <<<'PHEL'
            (ns app.repro
              (:require app.runner :as runner)
              (:require app.target :as target))

            (with-redefs [target/read-search (fn [] "first")]
              (println (runner/search-filter)))

            (with-redefs [target/read-search (fn [] "second")]
              (println (runner/search-filter)))
            PHEL;

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && php -d memory_limit=256M ' . escapeshellarg(dirname(__DIR__, 6) . '/bin/phel')
            . ' eval ' . escapeshellarg($source) . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
