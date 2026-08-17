<?php

declare(strict_types=1);

namespace Phel\Filesystem;

use Gacela\Framework\Attribute\PublicApi;
use Gacela\Framework\Health\ModuleHealthCheckInterface;

/**
 * The cross-module contract of the Filesystem module. Every other such
 * contract lives in `Phel\Shared\Facade\`; this one stays here because moving
 * it changes the public surface frozen in #2870, so it is declared public
 * where it is instead. Referencing it from another module's Factory or
 * Provider *is* going through a facade.
 */
#[PublicApi]
interface FilesystemFacadeInterface
{
    public function addFile(string $file): void;

    public function clearAll(): void;

    public function getTempDir(): string;

    public function getHealthCheck(): ModuleHealthCheckInterface;
}
