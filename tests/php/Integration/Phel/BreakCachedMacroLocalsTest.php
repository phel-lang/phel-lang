<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phel;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function mkdir;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

/**
 * `(break)` must hide macro locals whether the macro comes from source or from
 * the compiled-code cache. The cached macro is PHP that rebuilds its quoted
 * `x#` symbol at expansion time, so this runs `phel run` twice in a real
 * project: cold, then warm with only the caller recompiled.
 */
final class BreakCachedMacroLocalsTest extends TestCase
{
    use RemoveDirTrait;

    private const string MACROS = <<<'PHEL'
        (ns bp.macros)

        (defmacro with-auto [& body] `(let [hidden# 3] ~@body))
        (defmacro with-gensym [& body] (let [t (gensym "tmp")] `(let [~t 1] ~@body)))
        PHEL;

    private const string MAIN = <<<'PHEL'
        (ns bp.main
          (:require bp.macros :refer [with-auto with-gensym]))

        (let [result__2 4]
          (with-auto (with-gensym (break))))
        PHEL;

    private string $projectDir = '';

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/phel-break-cache-' . uniqid();
        mkdir($this->projectDir . '/src/bp', 0o777, true);
        file_put_contents($this->projectDir . '/src/bp/macros.phel', self::MACROS);
        file_put_contents($this->projectDir . '/src/bp/main.phel', self::MAIN);
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\nreturn (new \\Phel\\Config\\PhelConfig())->withSrcDirs(['src'])->withCacheDir(__DIR__ . '/cache');\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    public function test_break_hides_macro_locals_cold_and_with_the_macro_from_cache(): void
    {
        $this->runMain();
        self::assertSame(['result__2'], $this->breakpointLocalNames(), 'cold run');

        $cachedMacros = $this->compiledFile('bp.macros');
        touch($cachedMacros, 1_000_000_000);
        file_put_contents($this->projectDir . '/src/bp/main.phel', self::MAIN . "\n(println \"warm\")\n");

        $this->runMain();
        clearstatcache();
        self::assertSame(1_000_000_000, filemtime($cachedMacros), 'bp.macros must load from the cache');
        self::assertSame(['result__2'], $this->breakpointLocalNames(), 'warm run');
    }

    private function runMain(): void
    {
        $process = Subprocess::run(
            [PHP_BINARY, dirname(__DIR__, 4) . '/bin/phel', 'run', 'src/bp/main.phel'],
            $this->projectDir,
            '',
        );

        self::assertSame(0, $process->exitCode, sprintf("STDOUT:\n%s\nSTDERR:\n%s", $process->stdout, $process->stderr));
    }

    /**
     * @return list<string>
     */
    private function breakpointLocalNames(): array
    {
        $code = (string) file_get_contents($this->compiledFile('bp.main'));
        self::assertSame(1, preg_match('/::breakpoint\([^(]+\((.*?)\)\)/s', $code, $map), $code);
        preg_match_all('/"([^"]+)",/', $map[1], $names);

        return $names[1];
    }

    private function compiledFile(string $namespace): string
    {
        $files = glob($this->projectDir . '/cache/compiled/' . $namespace . '__*.php') ?: [];
        self::assertCount(1, $files, 'compiled file for ' . $namespace);

        return $files[0];
    }
}
