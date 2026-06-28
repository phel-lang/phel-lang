<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\ExtractorException;
use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Lang\Registry;
use Phel\Shared\NamespaceInformation;

use SplQueue;

use function array_key_exists;
use function in_array;
use function is_string;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

final class DependenciesForNamespace
{
    /**
     * Separates the individual items within one part of a memo key. A control
     * byte no directory path or namespace can contain.
     */
    private const string MEMO_ITEM_SEPARATOR = "\0";

    /**
     * Separates the directories part of a memo key from the seed-namespaces
     * part, so the two sets cannot collide across the boundary.
     */
    private const string MEMO_PART_SEPARATOR = "\x01";

    private const string CLOJURE_PREFIX = 'clojure.';

    private const string PHEL_PREFIX = 'phel.';

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
                    $queue->enqueue($this->resolveDependency($depNs, $currentNs, $index));
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
     * Resolves a declared dependency of `$requiringNs` to a namespace that the
     * walk can enqueue, throwing when it points at nothing loadable. A missing
     * `(:require ...)` was previously enqueued and then silently dropped from
     * the result, so a typo'd or absent dependency exited 0 with no feedback.
     *
     * A dependency resolves when it has a source file (`$index`), when a
     * `clojure.*` name maps to a bundled `phel.*` source (the same remap the
     * analyzer and {@see \Phel\Run\Application\FileRunner} apply), or when it is
     * already in the runtime registry (e.g. a lazily loaded bundled module with
     * no file in this scan).
     *
     * @param array<string, NamespaceInformation> $index
     */
    private function resolveDependency(string $depNs, string $requiringNs, array $index): string
    {
        if (array_key_exists($depNs, $index)) {
            return $depNs;
        }

        if (str_starts_with($depNs, self::CLOJURE_PREFIX)) {
            $phelNs = self::PHEL_PREFIX . substr($depNs, strlen(self::CLOJURE_PREFIX));
            if (array_key_exists($phelNs, $index)) {
                return $phelNs;
            }
        }

        if (Registry::getInstance()->hasNamespace(str_replace('-', '_', $depNs))) {
            return $depNs;
        }

        throw ExtractorException::cannotResolveRequiredNamespace($depNs, $requiringNs);
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

        return implode(self::MEMO_ITEM_SEPARATOR, $dirs)
            . self::MEMO_PART_SEPARATOR
            . implode(self::MEMO_ITEM_SEPARATOR, $seeds);
    }
}
