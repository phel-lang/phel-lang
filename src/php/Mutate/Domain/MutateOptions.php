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
     * @param list<string> $paths         `.phel` files or directories to mutate; empty = the project source dirs
     * @param list<string> $testPaths     test files or directories to run; empty = the project test dirs
     * @param list<string> $mutators      mutator ids to use; empty = all
     * @param float|null   $minCoveredMsi fail below this covered MSI (percent), the score over the mutants some test reaches
     * @param int          $workers       worker subprocesses to keep busy, at least 1
     * @param bool         $changed       mutate only the source files git reports as changed
     * @param string|null  $changedRef    with `changed`, the git ref to diff against (null: uncommitted, else the default branch)
     */
    public function __construct(
        public array $paths = [],
        public array $testPaths = [],
        public array $mutators = [],
        public float $timeoutFactor = 3.0,
        public ?float $minMsi = null,
        public ?float $minCoveredMsi = null,
        public int $workers = 1,
        public bool $changed = false,
        public ?string $changedRef = null,
    ) {}
}
