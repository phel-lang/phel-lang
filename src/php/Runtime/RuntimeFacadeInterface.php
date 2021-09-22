<?php

declare(strict_types=1);

namespace Phel\Runtime;

interface RuntimeFacadeInterface
{
    public function getRuntime(): RuntimeInterface;

    /**
     * @return array<string, list<string>> [ns => [path1, path2, ...]]
     *
     * @deprecated No replacement. Used only in RuntimeCommand, which is deprecated.
     */
    public function loadConfig(): array;

    /**
     * @deprecated No replacement. Used only in RuntimeCommand, which is deprecated.
     */
    public function getVendorDir(): string;

    /**
     * @internal for testing
     */
    public function addPath(string $namespacePrefix, array $path): void;
}
