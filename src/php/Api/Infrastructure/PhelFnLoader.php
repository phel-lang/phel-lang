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
     * @param list<string> $namespaces
     *
     * @return array<string,PersistentMapInterface>
     */
    public function getNormalizedPhelFunctions(array $namespaces = []): array
    {
        $this->loadAllPhelFunctions($namespaces);

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

    /**
     * TODO: instead of using a bool `$phelInternalDocLoaded`, we can optimize this by saving
     * which ns where loaded, ignore them and trigger the ones that were not yet generated.
     */
    private function loadAllPhelFunctions(array $namespaces): void
    {
        if (self::$phelInternalDocLoaded) {
            return;
        }

        $template = <<<EOF
# Simply require all namespaces that should be documented
(ns phel-internal\doc
  %REQUIRES%
)
EOF;
        $requireNamespaces = '';
        foreach ($namespaces as $ns) {
            $requireNamespaces .= "(:require {$ns})";
        }
        $docPhelContent = str_replace('%REQUIRES%', $requireNamespaces, $template);
        $phelFile = __DIR__ . '/phel/doc.phel';
        file_put_contents($phelFile, $docPhelContent);

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

        unlink($phelFile);
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
