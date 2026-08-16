<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\NamespaceInformation;

use function array_filter;
use function array_values;

/**
 * The namespaces of a run that at least one other namespace of the run
 * requires. The parallel runner evaluates exactly these in the parent
 * before dispatching, so a cold cache is warmed once for everything two
 * workers could otherwise compile at the same time; the leaves (the test
 * files themselves) go straight to the workers, each to exactly one.
 *
 * Two processes compiling the same namespace cold is not safe: `(load
 * ...)` secondaries and the per-namespace analyzer environment reach the
 * shared cache mid-compile, and the second process picks up a partial
 * picture (`Cannot resolve symbol 'defn-'`, "Macro ... is not callable").
 * Warming the shared prefix serially is what made the old parent-side
 * full pre-load work; this keeps that guarantee at a fraction of the cost.
 *
 * @internal
 */
final class SharedNamespaces
{
    /**
     * @param list<NamespaceInformation> $ordered
     *
     * @return list<NamespaceInformation> the subset of `$ordered`, in order
     */
    public static function of(array $ordered): array
    {
        $required = [];
        foreach ($ordered as $info) {
            foreach ($info->getDependencies() as $dependency) {
                $required[$dependency] = true;
            }
        }

        return array_values(array_filter(
            $ordered,
            static fn(NamespaceInformation $info): bool => isset($required[$info->getNamespace()]),
        ));
    }
}
