<?php

declare(strict_types=1);

namespace Phel\Command;

use Phel\Compiler\GlobalEnvironmentInterface;

interface CommandFacadeInterface
{
    public function executeReplCommand(GlobalEnvironmentInterface $globalEnv): void;

    public function executeRunCommand(string $fileOrPath): void;

    public function executeTestCommand(array $paths): void;
}
