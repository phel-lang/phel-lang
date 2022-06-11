<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
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
    }

    public function bench_phel_run(): void
    {
        Phel::run(__DIR__ . '/../../../../', 'phel\\core');
    }
}
