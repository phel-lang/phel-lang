<?php

declare(strict_types=1);

namespace Phel\Balance\Domain;

/**
 * A nesting level that was opened and never closed.
 *
 * `$openerText` and `$closerText` are not mirror images: `#(`, `#?(` and
 * `#?@(` all close with `)`, and `#{` closes with `}`.
 *
 * @internal
 */
final readonly class OpenDelimiter
{
    public function __construct(
        public string $openerText,
        public string $closerText,
        public int $line,
        public int $column,
        public int $offset = 0,
    ) {}
}
