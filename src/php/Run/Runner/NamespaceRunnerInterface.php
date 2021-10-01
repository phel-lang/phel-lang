<?php

declare(strict_types=1);

namespace Phel\Run\Runner;

interface NamespaceRunnerInterface
{
    public function run(string $namespace): void;
}
