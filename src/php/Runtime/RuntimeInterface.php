<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;

interface RuntimeInterface
{
    public function getEnv(): GlobalEnvironmentInterface;

    /**
     * @param string $namespacePrefix
     * @param array<int, string> $path
     */
    public function addPath(string $namespacePrefix, array $path): void;

    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array;
}
