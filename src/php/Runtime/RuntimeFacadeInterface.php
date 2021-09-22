<?php

declare(strict_types=1);

namespace Phel\Runtime;

interface RuntimeFacadeInterface
{
    public function getRuntime(): RuntimeInterface;

    public function addPath(string $namespacePrefix, array $path): void;

    public function loadConfig(): array;

    public function getVendorDir(): string;
}
