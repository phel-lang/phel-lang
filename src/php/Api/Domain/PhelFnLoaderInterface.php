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
     *     fnSignature?: string,
     *     desc?: string,
     *     url?: string,
     * }>
     */
    public function getNormalizedNativeSymbols(): array;
}
