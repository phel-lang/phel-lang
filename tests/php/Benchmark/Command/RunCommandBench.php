<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Command;

final class RunCommandBench
{
    public function benchRun(): void
    {
        $this->createCommandFactory()
            ->createRunCommand()
            ->addRuntimePath('test\\', [__DIR__ . '/Fixtures'])
            ->run('test\\test-script');
    }
}
