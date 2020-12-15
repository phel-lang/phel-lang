<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Compiler\GlobalEnvironmentInterface;
use Phel\Runtime\RuntimeInterface;

interface CommandFactoryInterface
{
    public function createReplCommand(GlobalEnvironmentInterface $globalEnv): ReplCommand;

    public function createRunCommand(RuntimeInterface $runtime): RunCommand;

    public function createTestCommand(RuntimeInterface $runtime): TestCommand;
}
