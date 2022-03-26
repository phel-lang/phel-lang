<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel\Build\BuildFacade;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Phel;

/**
 * @BeforeMethods("setUp")
 * @Iterations(2)
 * @Revs(5)
 */
final class PhelBench
{
    public function setUp(): void
    {
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        (new BuildFacade())->compileFile(
            __DIR__ . '/../../../../src/phel/core.phel',
            tempnam(sys_get_temp_dir(), 'phel-core')
        );
    }

    public function bench_phel_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\core');
    }
}
