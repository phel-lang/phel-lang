<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;

final readonly class DependenciesForNamespace
{
    public function __construct(
        private NamespaceExtractorInterface $namespaceExtractor,
    ) {
    }

    /**
     * @param  list<string>  $directories
     * @param  list<string>  $namespaces
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $namespaces): array
    {
        $allNamespaces = $this->namespaceExtractor->getNamespacesFromDirectories($directories);

        // Build a map of namespace => NamespaceInformation
        $namespaceMap = [];
        foreach ($allNamespaces as $info) {
            $namespaceMap[$info->getNamespace()] = $info;
        }

        // Traverse dependencies starting from the provided namespaces
        $toVisit = $namespaces;
        $visited = [];

        while ($toVisit !== []) {
            $current = array_shift($toVisit);
            if (isset($visited[$current])) {
                continue;
            }

            if (!isset($namespaceMap[$current])) {
                continue;
            }

            $visited[$current] = true;
            array_push($toVisit, ...$namespaceMap[$current]->getDependencies());
        }

        // Return only the required NamespaceInformation objects
        return array_values(array_filter(
            $allNamespaces,
            static fn (NamespaceInformation $info): bool => isset($visited[$info->getNamespace()]),
        ));
    }
}
