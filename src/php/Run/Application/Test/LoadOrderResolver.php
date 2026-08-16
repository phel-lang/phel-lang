<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\NamespaceInformation;

use function array_key_exists;
use function array_pop;
use function str_starts_with;

/**
 * Answers, for one namespace, which files a worker has to evaluate (and
 * in which order) before it can run that namespace's tests: the
 * namespace's transitive `(:require ...)` closure plus every bundled
 * `phel.*` namespace, restricted to the namespaces the parent already
 * resolved, in the parent's global dependency order, ending with the
 * namespace's own file(s).
 *
 * Bundled namespaces ride along unconditionally for the same reason the
 * serial runner seeds them all (`NamespaceCollector`): a test may reach
 * `phel.json/encode` fully qualified without a `(:require ...)`, and a
 * cached compiled artifact no longer triggers the lazy bundled resolver
 * at analysis time, so the definition has to be there before the tests run.
 *
 * The parent computes this once and ships it inside every work frame, so
 * a worker never re-runs a dependency walk of its own. Before this
 * existed each frame triggered a full walk in the worker whose memo never
 * hit (the seed differs per frame), which is what kept `--parallel` from
 * ever beating a serial run (#3203).
 *
 * @phpstan-type LoadEntry array{ns: string, file: string}
 *
 * @internal
 */
final class LoadOrderResolver
{
    /** Mirrors `BundledNamespaces::BUNDLED_NAMESPACE_PREFIX`; bundled modules are seeded for every namespace. */
    private const string BUNDLED_NAMESPACE_PREFIX = 'phel.';

    /** @var array<string, list<int>> namespace => indexes into the ordered list */
    private array $indexesByNamespace = [];

    /** @var array<string, list<LoadEntry>> memoized answers per namespace */
    private array $cache = [];

    /**
     * @param list<NamespaceInformation> $ordered every namespace of the run,
     *                                            dependencies before dependents
     */
    public function __construct(
        private readonly array $ordered,
    ) {
        foreach ($ordered as $index => $info) {
            $this->indexesByNamespace[$info->getNamespace()][] = $index;
        }
    }

    /**
     * @return list<LoadEntry>
     */
    public function loadOrderFor(NamespaceInformation $target): array
    {
        $ns = $target->getNamespace();
        if (array_key_exists($ns, $this->cache)) {
            return $this->cache[$ns];
        }

        $closure = $this->transitiveClosure($ns);

        $order = [];
        foreach ($this->ordered as $info) {
            if (isset($closure[$info->getNamespace()])) {
                $order[] = ['ns' => $info->getNamespace(), 'file' => $info->getFile()];
            }
        }

        return $this->cache[$ns] = $order;
    }

    /**
     * Namespaces reachable from `$root` through `(:require ...)`, root and
     * every bundled namespace included. Dependencies the parent did not
     * resolve (unknown names) are skipped; a require cycle terminates
     * because each namespace is visited once.
     *
     * @return array<string, true>
     */
    private function transitiveClosure(string $root): array
    {
        $seen = [$root => true];
        $stack = [$root];
        foreach ($this->indexesByNamespace as $ns => $_) {
            if (str_starts_with($ns, self::BUNDLED_NAMESPACE_PREFIX)) {
                $seen[$ns] = true;
                $stack[] = $ns;
            }
        }

        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($this->indexesByNamespace[$current] ?? [] as $index) {
                foreach ($this->ordered[$index]->getDependencies() as $dependency) {
                    if (isset($seen[$dependency])) {
                        continue;
                    }

                    if (!isset($this->indexesByNamespace[$dependency])) {
                        continue;
                    }

                    $seen[$dependency] = true;
                    $stack[] = $dependency;
                }
            }
        }

        return $seen;
    }
}
