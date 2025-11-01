<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * @BeforeMethods("setUp")
 */
final class PhelBench
{
    private ?string $compiledCorePath = null;

    public function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $this->compiledCorePath = tempnam(sys_get_temp_dir(), 'phel-core');
        (new BuildFacade())->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            $this->compiledCorePath,
        );
    }

    /**
     * @Revs(1)
     *
     * @Iterations(1)
     */
    public function bench_phel_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\core');
    }

    /**
     * @Revs(1)
     *
     * @Iterations(1)
     */
    public function bench_core_file_compilation(): void
    {
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        (new BuildFacade())->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
    }
}
