<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Registry;
use Phel\Lang\TypeFactory;
use Phel\Run\RunFacadeInterface;

use function dirname;

final class PhelFnLoader implements PhelFnLoaderInterface
{
    /** Prevent executing the internal doc multiple times. */
    private static bool $phelInternalDocLoaded = false;

    public function __construct(
        private RunFacadeInterface $runFacade,
    ) {
    }

    /**
     * @return array<string,PersistentMapInterface>
     */
    public function getNormalizedPhelFunctions(): array
    {
        $this->loadAllPhelFunctions();

        /** @var array<string,PersistentMapInterface> $normalizedData */
        $normalizedData = [];
        foreach ($this->getNamespaces() as $ns) {
            $normalizedNs = str_replace('phel\\', '', $ns);
            $moduleName = $normalizedNs === 'core' ? '' : $normalizedNs . '/';

            foreach ($this->getDefinitionsInNamespace($ns) as $fnName => $fn) {
                $fullFnName = $moduleName . $fnName;

                $normalizedData[$fullFnName] = $this->getPhelMeta($ns, $fnName);
            }
        }
        ksort($normalizedData);

        return $normalizedData;
    }

    private function loadAllPhelFunctions(): void
    {
        if (self::$phelInternalDocLoaded) {
            return;
        }

        $phelFile = __DIR__ . '/phel/doc.phel';

        $namespace = $this->runFacade
            ->getNamespaceFromFile($phelFile)
            ->getNamespace();

        $srcDirectories = [
            dirname($phelFile),
            ...$this->runFacade->getAllPhelDirectories(),
        ];

        $namespaceInformation = $this->runFacade->getDependenciesForNamespace(
            $srcDirectories,
            [$namespace, 'phel\\core'],
        );

        foreach ($namespaceInformation as $info) {
            $this->runFacade->evalFile($info);
        }

        self::$phelInternalDocLoaded = true;
    }

    /**
     * @return list<string>
     */
    private function getNamespaces(): array
    {
        return Registry::getInstance()->getNamespaces();
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefinitionsInNamespace(string $ns): array
    {
        return Registry::getInstance()->getDefinitionInNamespace($ns);
    }

    private function getPhelMeta(string $ns, string $fnName): PersistentMapInterface
    {
        return Registry::getInstance()->getDefinitionMetaData($ns, $fnName)
            ?? TypeFactory::getInstance()->emptyPersistentMap();
    }
}
