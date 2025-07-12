<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;
use SplQueue;

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
        $toVisit = new SplQueue();
        foreach ($namespaces as $ns) {
            $toVisit->enqueue($ns);
        }

        $visited = [];

        while (!$toVisit->isEmpty()) {
            $current = $toVisit->dequeue();
            if (isset($visited[$current])) {
                continue;
            }

            if (!isset($namespaceMap[$current])) {
                continue;
            }

            $visited[$current] = true;
            foreach ($namespaceMap[$current]->getDependencies() as $dep) {
                if (!isset($visited[$dep])) {
                    $toVisit->enqueue($dep);
                }
            }
        }

        // Return only the required NamespaceInformation objects
        return array_values(array_filter(
            $allNamespaces,
            static fn (NamespaceInformation $info): bool => isset($visited[$info->getNamespace()]),
        ));
    }
}
