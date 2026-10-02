<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function putenv;
use function strpos;
use function substr_count;
use function sys_get_temp_dir;
use function tempnam;

/**
 * A compiled-code cache file registers its top-level definitions in the global
 * environment with one call at its end, so a later file compiled from source
 * resolves them (#3470). A `def` that is not a top-level form keeps its own
 * guarded registration, because it registers only if it runs. Any other
 * top-level form may compile another file or throw, so the definitions before
 * it are registered first.
 */
final class CachedDefinitionRegistrationTest extends TestCase
{
    private const string SOURCE = <<<'PHEL'
        (ns probe.cached-defs)
        (def x 1)
        (defn f [] (def y 2) y)
        PHEL;

    private string $compiledFile = '';

    private string $compiledCode = '';

    protected function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
        new BuildFacade()->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            (string) tempnam(sys_get_temp_dir(), 'phel-core'),
        );

        $this->compiledCode = new CompilerFacade()->compileForCache(self::SOURCE, new CompileOptions())->getPhpCode();
        $this->compiledFile = (string) tempnam(sys_get_temp_dir(), 'phel-cached-defs');
        file_put_contents($this->compiledFile, "<?php\n" . $this->compiledCode);
    }

    public function test_top_level_defs_are_registered_by_one_call_at_the_end_of_the_file(): void
    {
        self::assertSame(1, substr_count($this->compiledCode, '->addCompiledDefinitions('));
        self::assertStringEndsWith(
            '::getInstance()->addCompiledDefinitions(["probe.cached_defs" => ["x", "f"]]);' . "\n",
            $this->compiledCode,
        );
        self::assertSame(1, substr_count($this->compiledCode, '->hasDefinition('), 'Only the nested def keeps its guard');
    }

    public function test_loading_the_file_registers_its_definitions_and_a_reload_is_harmless(): void
    {
        $env = GlobalEnvironmentSingleton::initializeNew();

        require $this->compiledFile;
        require $this->compiledFile;

        $definitions = $env->snapshot()['definitions']['probe.cached_defs'] ?? [];
        self::assertArrayHasKey('x', $definitions);
        self::assertArrayHasKey('f', $definitions);
        self::assertArrayNotHasKey('y', $definitions, 'A nested def registers only once it runs');
    }

    public function test_definitions_before_a_form_that_throws_are_registered(): void
    {
        $source = <<<'PHEL_WRAP'
        (ns probe.cached-throw)
        (def a 1)
        (defn g [] a)
        (when (php/getenv "PHEL_PROBE_CACHED_THROW") (throw (RuntimeException. "boom")))
        (def b 2)
        PHEL_WRAP;
        $code = new CompilerFacade()->compileForCache($source, new CompileOptions())->getPhpCode();
        $file = (string) tempnam(sys_get_temp_dir(), 'phel-cached-throw');
        file_put_contents($file, "<?php\n" . $code);
        $env = GlobalEnvironmentSingleton::initializeNew();

        putenv('PHEL_PROBE_CACHED_THROW=1');
        try {
            require $file;
            self::fail('The top-level throw should stop the file');
        } catch (RuntimeException) {
        } finally {
            putenv('PHEL_PROBE_CACHED_THROW');
        }

        $definitions = $env->snapshot()['definitions']['probe.cached_throw'] ?? [];
        self::assertArrayHasKey('a', $definitions);
        self::assertArrayHasKey('g', $definitions);
        self::assertArrayNotHasKey('b', $definitions, 'A definition after the throw never ran');
    }

    public function test_a_def_whose_value_runs_code_flushes_the_pending_batch_first(): void
    {
        $source = <<<'PHEL'
            (ns probe.cached-flush)
            (def a 1)
            (def b (php/strlen "ab"))
            (def c 3)
            PHEL;
        $code = new CompilerFacade()->compileForCache($source, new CompileOptions())->getPhpCode();

        self::assertSame(2, substr_count($code, '->addCompiledDefinitions('));
        self::assertLessThan(
            (int) strpos($code, 'strlen('),
            (int) strpos($code, 'addCompiledDefinitions(["probe.cached_flush" => ["a"]]);'),
        );
        self::assertStringEndsWith('addCompiledDefinitions(["probe.cached_flush" => ["c"]]);' . "\n", $code);
        self::assertSame(1, substr_count($code, '->hasDefinition('), 'b registers where it runs');
    }
}
