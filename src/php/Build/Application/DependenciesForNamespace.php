<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Shared\NamespaceInformation;

use SplQueue;

use function array_key_exists;
use function in_array;
use function is_string;

final class DependenciesForNamespace
{
    /**
     * Intra-process memo keyed by `(dirs, seeds)` so the three root callers
     * (`FileRunner`, `DataReadersLoader`, `NamespaceLoader`) don't each re-derive
     * the same transitive dependency closure within one process.
     *
     * @var array<string, list<NamespaceInformation>>
     */
    private array $memo = [];

    public function __construct(
        private readonly NamespaceExtractorInterface $namespaceExtractor,
    ) {}

    /**
     * @param list<string> $directories
     * @param list<string> $ns
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        $memoKey = $this->memoKey($directories, $ns);
        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($directories);

        $index = [];
        $queue = new SplQueue();
        $seenInQueue = [];
        foreach ($namespaceInformation as $info) {
            // Dependencies are declared on primary `(ns ...)` definitions;
            // secondaries only join an existing namespace via `(in-ns ...)`.
            if (!$info->isPrimaryDefinition()) {
                continue;
            }

            $index[$info->getNamespace()] = $info;

            if (in_array($info->getNamespace(), $ns) && !isset($seenInQueue[$info->getNamespace()])) {
                $queue->enqueue($info->getNamespace());
                $seenInQueue[$info->getNamespace()] = true;
            }
        }

        $requiredNamespaces = [];
        while (!$queue->isEmpty()) {
            $currentNs = $queue->dequeue();
            if (!is_string($currentNs)) {
                continue;
            }

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
            if (!$info->isPrimaryDefinition()) {
                // Secondaries join an existing namespace via `(in-ns ...)`
                // and are pulled in by the primary's `(load ...)` forms —
                // runtime callers only need the one primary per namespace.
                continue;
            }

            if (isset($requiredNamespaces[$info->getNamespace()])) {
                $result[] = $info;
            }
        }

        return $this->memo[$memoKey] = $result;
    }

    /**
     * @param list<string> $directories
     * @param list<string> $ns
     */
    private function memoKey(array $directories, array $ns): string
    {
        $dirs = $directories;
        sort($dirs);
        $seeds = $ns;
        sort($seeds);

        return implode("\0", $dirs) . "\x01" . implode("\0", $seeds);
    }
}
