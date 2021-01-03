<?php

declare(strict_types=1);

namespace Phel\Runtime;

use Phel\Compiler\GlobalEnvironmentInterface;

interface RuntimeInterface
{
    public function getEnv(): GlobalEnvironmentInterface;

    /**
     * @param string $namespacePrefix
     * @param string[] $path
     */
    public function addPath(string $namespacePrefix, array $path): void;

    /**
     * @return bool true if the namespace was successfully loaded; false otherwise
     */
    public function loadNs(string $ns): bool;
}
