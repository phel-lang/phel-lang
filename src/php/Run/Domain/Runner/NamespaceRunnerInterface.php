<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Runner;

interface NamespaceRunnerInterface
{
    public function run(string $namespace): void;
}
