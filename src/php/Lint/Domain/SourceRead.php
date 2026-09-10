<?php

declare(strict_types=1);

namespace Phel\Lint\Domain;

use Phel\Lang\TypeInterface;

/**
 * What reading one file produced: the top-level forms the rules inspect, the
 * namespace they belong to, and whether reading stopped early.
 *
 * Reading is best-effort, so a file whose later forms are broken still hands
 * the rules the ones that read. `failed` is how the runner learns that the
 * rest of the file was never seen, which is the difference between a file with
 * no findings and a file nobody could look at (#3292).
 *
 * @internal
 */
final readonly class SourceRead
{
    /**
     * @param list<bool|float|int|string|TypeInterface|null> $forms
     */
    public function __construct(
        public string $namespace,
        public array $forms,
        public bool $failed,
    ) {}
}
