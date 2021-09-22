<?php

declare(strict_types=1);

namespace Phel\Runtime;

interface RuntimeFacadeInterface
{
    public function getRuntime(): RuntimeInterface;

    /**
     * @internal for testing
     */
    public function addPath(string $namespacePrefix, array $path): void;
}
