<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain;

/**
 * @internal
 */
final readonly class MutationPlan
{
    /**
     * @param list<string> $sourceFiles    `.phel` files whose definitions are mutated
     * @param list<string> $loadOrder      every file the worker evaluates, dependencies first
     * @param list<string> $testNamespaces namespaces run against every mutant
     */
    public function __construct(
        public array $sourceFiles,
        public array $loadOrder,
        public array $testNamespaces,
    ) {}
}
