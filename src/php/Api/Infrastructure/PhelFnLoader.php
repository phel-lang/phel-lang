<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure;

use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Run\RunFacadeInterface;

use function dirname;
use function in_array;
use function sprintf;

final readonly class PhelFnLoader implements PhelFnLoaderInterface
{
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
        $containsCoreFunctions = in_array('phel\\core', $namespaces, true);

        /** @var array<string,PersistentMapInterface> $normalizedData */
        $normalizedData = [];
        foreach ($this->getNamespaces() as $ns) {
            if (!$containsCoreFunctions && $ns === 'phel\\core') {
                continue;
            }

            $normalizedNs = str_replace('phel\\', '', $ns);
            $moduleName = $normalizedNs === 'core' ? '' : $normalizedNs . '/';

            foreach (array_keys($this->getDefinitionsInNamespace($ns)) as $fnName) {
                $fullFnName = $moduleName . $fnName;

                $normalizedData[$fullFnName] = $this->getPhelMeta($ns, $fnName);
            }
        }

        ksort($normalizedData);

        return $normalizedData;
    }

    private function loadAllPhelFunctions(array $namespaces): void
    {
        Registry::getInstance()->clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();

        $template = <<<EOF
# Simply require all namespaces that should be documented
(ns phel-internal\doc
  %REQUIRES%
)
EOF;
        $requireNamespaces = '';
        foreach ($namespaces as $ns) {
            $requireNamespaces .= sprintf('(:require %s)', $ns);
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
