<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Build\Domain\Extractor\NamespaceInformation;

use SplQueue;

use function array_key_exists;
use function in_array;

final readonly class DependenciesForNamespace
{
    public function __construct(
        private NamespaceExtractorInterface $namespaceExtractor,
    ) {
    }

    /**
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($directories);

        $index = [];
        $queue = new SplQueue();
        foreach ($namespaceInformation as $info) {
            $index[$info->getNamespace()] = $info;
            if (in_array($info->getNamespace(), $ns)) {
                $queue->enqueue($info->getNamespace());
            }
        }

        $requiredNamespaces = [];
        while (!$queue->isEmpty()) {
            $currentNs = $queue->dequeue();

            if (!array_key_exists($currentNs, $requiredNamespaces)
                && array_key_exists($currentNs, $index)
            ) {
                foreach ($index[$currentNs]->getDependencies() as $depNs) {
                    $queue->enqueue($depNs);
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
