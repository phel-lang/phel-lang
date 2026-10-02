<?php

declare(strict_types=1);

namespace Phel\Build\Application;

use Phel\Build\Domain\Extractor\NamespaceExtractorInterface;
use Phel\Shared\FrameworkNamespaces;
use Phel\Shared\Munge;
use Phel\Shared\NamespaceInformation;

use SplQueue;

use function array_key_exists;
use function array_keys;
use function array_map;
use function in_array;

/**
 * @internal
 */
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
        private readonly BundledNamespaceIndex $bundledNamespaces,
        private readonly MissingRequireReporter $missingRequireReporter,
    ) {}

    /**
     * @param list<string> $directories
     * @param list<string> $ns
     *
     * @return list<NamespaceInformation>
     */
    public function getDependenciesForNamespace(array $directories, array $ns): array
    {
        // Seeds, index keys and declared dependencies are all matched as
        // strings, so both namespace separators have to collapse onto one
        // form first. A caller handing over the legacy backslash form
        // (`app\main`) otherwise matches nothing and gets an empty result with
        // no error; `Watch`'s file-change resolver produces exactly that form.
        $ns = array_map(Munge::canonicalNs(...), $ns);

        $memoKey = $this->memoKey($directories, $ns);
        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $namespaceInformation = $this->namespaceExtractor->getNamespacesFromDirectories($directories);

        $index = [];
        /** @var SplQueue<string> $queue */
        $queue = new SplQueue();
        $seenInQueue = [];
        foreach ($namespaceInformation as $info) {
            // Dependencies are declared on primary `(ns ...)` definitions;
            // secondaries only join an existing namespace via `(in-ns ...)`.
            if (!$info->isPrimaryDefinition()) {
                continue;
            }

            $canonical = Munge::canonicalNs($info->getNamespace());
            $index[$canonical] = $info;

            if (in_array($canonical, $ns, true) && !isset($seenInQueue[$canonical])) {
                $queue->enqueue($canonical);
                $seenInQueue[$canonical] = true;
            }
        }

        // A `clojure.*` seed stands for its `phel.*` target, as a declared
        // dependency does; the emitted `ns` form hands one over as is.
        foreach ($ns as $seed) {
            $target = FrameworkNamespaces::clojureTarget($seed);
            if ($target !== null
                && !isset($index[$seed])
                && isset($index[$target])
                && !isset($seenInQueue[$target])
            ) {
                $queue->enqueue($target);
                $seenInQueue[$target] = true;
            }
        }

        $requiredNamespaces = [];
        while (!$queue->isEmpty()) {
            $currentNs = $queue->dequeue();
            if (!array_key_exists($currentNs, $requiredNamespaces)
                && array_key_exists($currentNs, $index)
            ) {
                foreach ($index[$currentNs]->getDependencies() as $depNs) {
                    $queue->enqueue($this->resolveDependency($depNs, $currentNs, $index, $directories));
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

            if (isset($requiredNamespaces[Munge::canonicalNs($info->getNamespace())])) {
                $result[] = $info;
            }
        }

        return $this->memo[$memoKey] = $result;
    }

    /**
     * Resolves a declared dependency of `$requiringNs` to the namespace the walk
     * should enqueue, throwing when it points at nothing loadable. Such a
     * dependency was previously enqueued and then silently dropped, so a
     * typo'd or absent `(:require ...)` exited 0 with no feedback.
     *
     * @param array<string, NamespaceInformation> $index
     * @param list<string>                        $directories
     */
    private function resolveDependency(string $depNs, string $requiringNs, array $index, array $directories): string
    {
        $depNs = Munge::canonicalNs($depNs);

        if (array_key_exists($depNs, $index)) {
            return $depNs;
        }

        $target = FrameworkNamespaces::clojureTarget($depNs);
        if ($target !== null && array_key_exists($target, $index)) {
            return $target;
        }

        if ($this->bundledNamespaces->resolvesWithoutSource($depNs)) {
            return $target ?? $depNs;
        }

        throw $this->missingRequireReporter->error(
            $this->bundledNamespaces->missingNamespaceMessage($depNs, $requiringNs, array_keys($index)),
            $depNs,
            $index[$requiringNs]->getFile(),
            $directories,
        );
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
