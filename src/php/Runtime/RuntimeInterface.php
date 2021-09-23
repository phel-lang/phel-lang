<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;

interface RuntimeInterface
{
    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array;

    public function getEnv(): GlobalEnvironmentInterface;

    /**
     * @param string $namespacePrefix
     * @param array<int, string> $paths
     */
    public function addPath(string $namespacePrefix, array $paths): void;
}
