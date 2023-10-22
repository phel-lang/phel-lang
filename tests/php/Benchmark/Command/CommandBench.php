<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Command;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Phel\Run\Infrastructure\Command\RunCommand;
use Phel\Run\Infrastructure\Command\TestCommand;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @BeforeMethods("setUp")
 *
 * @Iterations(3)
 *
 * @Revs(1)
 */
final class CommandBench
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, GacelaConfig::defaultPhpConfig());
    }

    public function bench_run_command(): void
    {
        ob_start();
        (new RunCommand())->run(
            new StringInput(__DIR__ . '/fixtures/run-command.phel'),
            new NullOutput(),
        );
        ob_clean();
    }

    public function bench_test_command(): void
    {
        ob_start();
        (new TestCommand())->run(
            new StringInput(__DIR__ . '/fixtures/test-command.phel'),
            new NullOutput(),
        );
        ob_clean();
    }
}
