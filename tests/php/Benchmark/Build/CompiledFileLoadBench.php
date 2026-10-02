<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Build;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Shared\CompiledSourceHash;
use Phel\Shared\CompileOptions;
use PhelTest\Benchmark\Compiler\Fixtures\CoreCorpus;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

use function file_put_contents;
use function implode;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

/**
 * The two per-file costs a warm process pays for every compiled-code cache
 * hit: hashing the source to find the entry, and running the entry, which
 * registers each top-level `def` in the global environment besides the
 * registry. The loaded file holds plain `(def vN N)` forms so the value of
 * each definition costs next to nothing and the registration is what is left.
 *
 * @BeforeMethods("setUp")
 */
final class CompiledFileLoadBench
{
    private const int DEFINITIONS = 500;

    private string $compiledFile = '';

    private string $source = '';

    public function setUp(): void
    {
        Phel::bootstrap(__DIR__ . '/../../../../');
        GlobalEnvironmentSingleton::initializeNew();

        $forms = ['(ns bench.compiled-file-load)'];
        for ($i = 0; $i < self::DEFINITIONS; ++$i) {
            $forms[] = sprintf('(def v%d %d)', $i, $i);
        }

        $result = new CompilerFacade()->compileForCache(implode("\n", $forms), new CompileOptions());

        $this->compiledFile = sys_get_temp_dir() . '/phel-compiled-file-load-' . uniqid() . '.php';
        file_put_contents($this->compiledFile, "<?php\n" . $result->getCodeWithSourceMap());

        $this->source = CoreCorpus::source();
    }

    /**
     * A first load of the file into a fresh environment, the shape of a warm
     * process loading a namespace from the compiled-code cache.
     *
     * @Revs(20)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     */
    public function bench_load_compiled_file_with_many_defs(): void
    {
        GlobalEnvironmentSingleton::initializeNew();
        require $this->compiledFile;
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     *
     * @Warmup(2)
     */
    public function bench_compiled_source_hash(): void
    {
        CompiledSourceHash::of($this->source, 0);
    }
}
