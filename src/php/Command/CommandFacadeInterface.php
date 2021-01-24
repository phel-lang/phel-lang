<?php

declare(strict_types=1);

namespace Phel\Command;

interface CommandFacadeInterface
{
    public function executeReplCommand(): void;

    public function executeRunCommand(string $fileOrPath): void;

    public function executeTestCommand(array $paths): void;

    public function executeFormatCommand(array $paths): void;
}
