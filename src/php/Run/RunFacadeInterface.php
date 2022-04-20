<?php

declare(strict_types=1);

namespace Phel\Run;

use Phel\Run\Command\ReplCommand;
use Phel\Run\Command\RunCommand;

interface RunFacadeInterface
{
    public function getReplCommand(): ReplCommand;

    public function getRunCommand(): RunCommand;

    public function runNamespace(string $namespace): void;
}
