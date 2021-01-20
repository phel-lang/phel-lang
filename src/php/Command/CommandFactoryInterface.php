<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Runtime\RuntimeInterface;

interface CommandFactoryInterface
{
    public function createReplCommand(RuntimeInterface $runtime): ReplCommand;

    public function createRunCommand(RuntimeInterface $runtime): RunCommand;

    public function createTestCommand(RuntimeInterface $runtime): TestCommand;

    public function createFormatCommand(): FormatCommand;
}
