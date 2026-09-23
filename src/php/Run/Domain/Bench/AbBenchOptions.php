<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Bench;

/**
 * What `phel bench --ab` was asked to do. `$benchArguments` are the options
 * both sides receive unchanged (`--filter`, `--revs`, `--iterations`,
 * `--warmup`), already rendered as `--name=value`.
 *
 * @internal
 */
final readonly class AbBenchOptions
{
    /**
     * @param list<string> $paths          the paths as given, relative to the working directory or absolute
     * @param list<string> $benchArguments
     */
    public function __construct(
        public string $ref,
        public int $pairs,
        public array $paths,
        public array $benchArguments,
        public ?float $tolerance,
    ) {}
}
