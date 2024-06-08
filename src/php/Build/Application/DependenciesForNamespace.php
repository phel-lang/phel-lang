<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\NamespaceInformation;

use function array_key_exists;
use function in_array;

final readonly class DependenciesForNamespace
{
    public function __construct(
        private NamespaceExtractor $namespaceExtractor,
    ) {
    }

    /**
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($directories);

        $index = [];
        $queue = [];
        foreach ($namespaceInformation as $info) {
            $index[$info->getNamespace()] = $info;
            if (in_array($info->getNamespace(), $ns)) {
                $queue[] = $info->getNamespace();
            }
        }

        $requiredNamespaces = [];
        while ($queue !== []) {
            $currentNs = array_shift($queue);

            if (!array_key_exists($currentNs, $requiredNamespaces)
                && array_key_exists($currentNs, $index)
            ) {
                foreach ($index[$currentNs]->getDependencies() as $depNs) {
                    $queue[] = $depNs;
                }
            }

            $requiredNamespaces[$currentNs] = true;
        }

        $result = [];
        foreach ($namespaceInformation as $info) {
            if (isset($requiredNamespaces[$info->getNamespace()])) {
                $result[] = $info;
            }
        }

        return $result;
    }
}
