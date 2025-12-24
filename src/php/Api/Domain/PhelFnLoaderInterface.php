<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Lang\Collections\Map\PersistentMapInterface;

interface PhelFnLoaderInterface
{
    /**
     * @param list<string> $namespaces
     *
     * @return array<string,PersistentMapInterface>
     */
    public function getNormalizedPhelFunctions(array $namespaces = []): array;

    /**
     * @return array<string,array{
     *     doc?: string,
     *     signatures?: list<string>,
     *     desc?: string,
     *     docUrl?: string,
     *     example?: string,
     *     file?: string,
     *     line?: int,
     * }>
     */
    public function getNormalizedNativeSymbols(): array;

    public function loadAllPhelFunctions(array $namespaces): void;
}
