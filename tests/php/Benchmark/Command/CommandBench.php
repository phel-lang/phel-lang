<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Command;

use Phel\Phel;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @BeforeMethods("setUp")
 */
final class CommandBench
{
    public function setUp(): void
    {
        Phel::bootstrap(__DIR__);
    }

    /**
     * End-to-end `phel run <file>` cold start. Exercises the full CLI
     * command path — argument parsing, namespace resolution, `(load ...)`
     * chain — so it catches regressions `PhelBench::bench_phel_run` may
     * miss (e.g. Symfony Console / Gacela bootstrap overhead).
     *
     * @Revs(1)
     *
     * @Iterations(10)
     *
     * @Warmup(1)
     */
    public function bench_run_command(): void
    {
        ob_start();
        try {
            (new RunCommand())->run(
                new StringInput(__DIR__ . '/fixtures/run-command.phel'),
                new NullOutput(),
            );
        } finally {
            ob_end_clean();
        }
    }

    /**
     * End-to-end `phel test <file>` cold start.
     *
     * @Revs(1)
     *
     * @Iterations(10)
     *
     * @Warmup(1)
     */
    public function bench_test_command(): void
    {
        ob_start();
        try {
            (new TestCommand())->run(
                new StringInput(__DIR__ . '/fixtures/test-command.phel'),
                new NullOutput(),
            );
        } finally {
            ob_end_clean();
        }
    }
}
