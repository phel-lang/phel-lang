<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Runner;

interface NamespaceRunnerInterface
{
    /**
     * @param list<string> $importPaths
     */
    public function run(string $namespace, array $importPaths = []): void;
}
