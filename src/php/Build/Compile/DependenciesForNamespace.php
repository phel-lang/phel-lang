<?php

declare(strict_types=1);

namespace Phel\Build\Compile;

use Phel\Build\Extractor\NamespaceExtractor;
use Phel\Build\Extractor\NamespaceInformation;

final class DependenciesForNamespace
{
    private NamespaceExtractor $namespaceExtractor;

    public function __construct(NamespaceExtractor $namespaceExtractor)
    {
        $this->namespaceExtractor = $namespaceExtractor;
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
        while (count($queue) > 0) {
            $currentNs = array_shift($queue);
            if (!isset($requiredNamespaces[$currentNs])) {
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
