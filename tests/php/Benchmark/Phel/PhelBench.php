<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * @BeforeMethods("setUp")
 *
 * @Iterations(2)
 *
 * @Revs(5)
 */
final class PhelBench
{
    public function setUp(): void
    {
        Phel::bootstrap(__DIR__);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        (new BuildFacade())->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core'),
        );
    }

    public function bench_phel_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\core');
    }
}
