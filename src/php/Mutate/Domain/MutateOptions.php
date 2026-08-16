<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain;

/**
 * Everything `phel mutate` was asked to do, resolved from the CLI.
 *
 * @internal
 */
final readonly class MutateOptions
{
    /**
     * @param list<string> $paths     `.phel` files or directories to mutate; empty = the project source dirs
     * @param list<string> $testPaths test files or directories to run; empty = the project test dirs
     * @param list<string> $mutators  mutator ids to use; empty = all
     */
    public function __construct(
        public array $paths = [],
        public array $testPaths = [],
        public array $mutators = [],
        public float $timeoutFactor = 3.0,
        public ?float $minMsi = null,
    ) {}
}
