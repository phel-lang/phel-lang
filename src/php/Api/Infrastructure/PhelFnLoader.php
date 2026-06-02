<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phel;
use Phel\Api\Domain\PhelFnLoaderInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;

use function in_array;

final readonly class PhelFnLoader implements PhelFnLoaderInterface
{
    public function __construct(
        private PhelFunctionRuntimeLoader $runtimeLoader,
    ) {}

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
    public function getNormalizedNativeSymbols(): array
    {
        return NativeSymbolCatalog::definitions();
    }

    /**
     * @param list<string> $namespaces
     *
     * @return array<string,PersistentMapInterface<mixed, mixed>>
     */
    public function getNormalizedPhelFunctions(array $namespaces = []): array
    {
        $this->loadAllPhelFunctions($namespaces);
        $containsCoreFunctions = in_array('phel.core', $namespaces, true);

        /** @var array<string,PersistentMapInterface<mixed, mixed>> $normalizedData */
        $normalizedData = [];
        foreach ($this->getNamespaces() as $ns) {
            if (!$containsCoreFunctions && $ns === 'phel.core') {
                continue;
            }

            $normalizedNs = str_replace('phel.', '', $ns);
            $moduleName = $normalizedNs === 'core' ? '' : $normalizedNs . '/';

            foreach (array_keys($this->getDefinitionsInNamespace($ns)) as $fnName) {
                $fullFnName = $moduleName . $fnName;

                $normalizedData[$fullFnName] = $this->getPhelMeta($ns, $fnName);
            }
        }

        ksort($normalizedData);

        return $normalizedData;
    }

    /**
     * @param list<string> $namespaces
     */
    public function loadAllPhelFunctions(array $namespaces): void
    {
        $this->runtimeLoader->load($namespaces);
    }

    /**
     * @return list<string>
     */
    private function getNamespaces(): array
    {
        return Phel::getNamespaces();
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefinitionsInNamespace(string $ns): array
    {
        return Phel::getDefinitionInNamespace($ns);
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>
     */
    private function getPhelMeta(string $ns, string $fnName): PersistentMapInterface
    {
        return Phel::getDefinitionMetaData($ns, $fnName)
            ?? Phel::map();
    }
}
