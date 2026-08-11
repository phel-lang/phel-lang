<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

/**
 * A closing delimiter with nothing to close, or one that closes a different
 * kind than the innermost open level.
 *
 * `$openDelimiter` is null when the stack was empty, and carries the level
 * that was actually open when the kinds disagree (`(foo]`).
 *
 * @internal
 */
final readonly class UnexpectedCloser
{
    public function __construct(
        public string $closerText,
        public ?OpenDelimiter $openDelimiter,
        public int $line,
        public int $column,
    ) {}
}
