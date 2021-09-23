<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;

interface RuntimeFacadeInterface
{
    /**
     * @return list<string>
     */
    public function getSourceDirectories(): array;

    public function getEnv(): GlobalEnvironmentInterface;

    /**
     * @internal for testing
     */
    public function addPath(string $namespacePrefix, array $path): void;
}
