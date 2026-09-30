<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use PhelTest\Support\RemoveDirTrait;
use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function file_put_contents;
use function mkdir;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

/**
 * A call to a fn defined earlier in the same file is arity-checked on every
 * compile path. The check used to read only the runtime registry: `phel
 * compile` never evaluates, so the fn was not there yet, and the lookup used
 * the unmunged namespace, so a dashed one like `same-file.core` missed on
 * `run` and `build` too (#3394).
 */
final class SameFileArityCheckTest extends TestCase
{
    use RemoveDirTrait;

    private const string VALID_CALLS = <<<'PHEL'
        (ns same-file.core)
        (declare later)
        (defn early [] (later 1 2))
        (defn later [a] a)
        (defn- sq [n] (* n n))
        (defn pick ([a] a) ([a b] b))
        (defn rest-of [a & more] more)
        (defn main [] [(sq 3) (pick 1) (pick 1 2) (later 1) (rest-of 1 2 3)])
        PHEL;

    private string $repoRoot;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 4);
        $this->projectDir = sys_get_temp_dir() . '/phel-same-file-arity-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir . '/src', 0o755, true);

        mkdir($this->projectDir . '/vendor', 0o755, true);
        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );

        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\n\ndeclare(strict_types=1);\n\n"
            . "use Phel\\Config\\PhelConfig;\n\n"
            . "return new PhelConfig()->withSrcDirs(['src'])->withVendorDir('');\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->projectDir);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideWrongCalls(): iterable
    {
        yield 'too many to a single arity' => ["(defn sq [n] (* n n))\n(sq 3 4)", 'Got: 2. Expected: 1'];
        yield 'too few to a single arity' => ["(defn sq [n] (* n n))\n(sq)", 'Got: 0. Expected: 1'];
        yield 'too many to a multi arity' => ["(defn pick ([a] a) ([a b] b))\n(pick 1 2 3)", 'Got: 3. Expected: 1 or 2'];
        yield 'too few to a variadic' => ["(defn later [a & more] a)\n(later)", 'Got: 0. Expected: at least 1'];
        yield 'too many to a private fn' => ["(defn- sq [n] (* n n))\n(sq 3 4)", 'Got: 2. Expected: 1'];
    }

    #[DataProvider('provideWrongCalls')]
    public function test_compile_reports_a_wrong_call(string $body, string $expected): void
    {
        $this->writeCore($body);

        $this->assertWrongArity($expected, $this->phel('compile', 'src/core.phel'));
    }

    /**
     * `run` and `build` evaluate each form, so two cases cover them.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideEvaluatedWrongCalls(): iterable
    {
        yield 'too many' => ["(defn sq [n] (* n n))\n(sq 3 4)", 'Got: 2. Expected: 1'];
        yield 'too few' => ["(defn sq [n] (* n n))\n(sq)", 'Got: 0. Expected: 1'];
    }

    #[DataProvider('provideEvaluatedWrongCalls')]
    public function test_run_reports_a_wrong_call(string $body, string $expected): void
    {
        $this->writeCore($body);

        $this->assertWrongArity($expected, $this->phel('run', 'src/core.phel'));
    }

    #[DataProvider('provideEvaluatedWrongCalls')]
    public function test_build_without_cache_reports_a_wrong_call(string $body, string $expected): void
    {
        $this->writeCore($body);

        $this->assertWrongArity($expected, $this->phel('build', '--no-cache'));
    }

    public function test_cached_build_reports_a_wrong_call_added_later(): void
    {
        $this->writeCore("(defn sq [n] (* n n))\n(sq 3)");
        $this->assertSucceeds($this->phel('build'));

        $this->writeCore("(defn sq [n] (* n n))\n(sq 3 4)");

        $this->assertWrongArity('Got: 2. Expected: 1', $this->phel('build'));
    }

    public function test_every_path_accepts_calls_that_match_an_arity(): void
    {
        file_put_contents($this->projectDir . '/src/core.phel', self::VALID_CALLS);

        $this->assertSucceeds($this->phel('compile', 'src/core.phel'));
        $this->assertSucceeds($this->phel('run', 'src/core.phel'));
        $this->assertSucceeds($this->phel('build', '--no-cache'));
        $this->assertSucceeds($this->phel('build'));
        $this->assertSucceeds($this->phel('build'));
    }

    private function writeCore(string $body): void
    {
        file_put_contents($this->projectDir . '/src/core.phel', "(ns same-file.core)\n" . $body . "\n");
    }

    private function assertWrongArity(string $expected, Subprocess $process): void
    {
        $output = $process->stdout . $process->stderr;

        self::assertNotSame(0, $process->exitCode, $output);
        self::assertStringContainsString('Wrong number of arguments to function', $output);
        self::assertStringContainsString($expected, $output);
    }

    private function assertSucceeds(Subprocess $process): void
    {
        self::assertSame(0, $process->exitCode, $process->stdout . $process->stderr);
    }

    private function phel(string ...$arguments): Subprocess
    {
        return Subprocess::run(
            ['php', '-d', 'memory_limit=256M', $this->repoRoot . '/bin/phel', ...$arguments],
            $this->projectDir,
        );
    }
}
