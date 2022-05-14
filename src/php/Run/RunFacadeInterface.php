<?php

declare(strict_types=1);

namespace Phel\Run;

use Phel\Run\Infrastructure\Command\ReplCommand;

interface RunFacadeInterface
{
    public function getReplCommand(): ReplCommand;

    public function runNamespace(string $namespace): void;
}
