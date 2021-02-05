<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Command\Format\FormatCommand;
use Phel\Command\Repl\ReplCommand;
use Phel\Command\Run\RunCommand;
use Phel\Command\Test\TestCommand;
use Phel\Runtime\RuntimeInterface;

interface CommandFactoryInterface
{
    public function createReplCommand(RuntimeInterface $runtime): ReplCommand;

    public function createRunCommand(RuntimeInterface $runtime): RunCommand;

    public function createTestCommand(RuntimeInterface $runtime): TestCommand;

    public function createFormatCommand(): FormatCommand;
}
