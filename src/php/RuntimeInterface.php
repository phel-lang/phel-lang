<?php

declare(strict_types=1);

namespace Phel;

interface RuntimeInterface
{
    public function getEnv(): GlobalEnvironment;

    public function addPath(string $namespacePrefix, array $path): void;

    /**
     * @return bool true if the namespace was successfully loaded; false otherwise
     */
    public function loadNs(string $ns): bool;
}
