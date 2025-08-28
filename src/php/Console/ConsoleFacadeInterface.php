<?php

declare(strict_types=1);

namespace Phel\Console;

interface ConsoleFacadeInterface
{
    public function getVersion(): string;

    public function runConsole(): void;
}
