<?php

declare(strict_types=1);

namespace Phel\Balance\Application;

/**
 * Static facts about the original token stream the search walks: which closers
 * are ambiguous (a mismatched closer with no parent level) and whether the file
 * had any surplus closer at all.
 *
 * @internal
 */
final readonly class PrePass
{
    /**
     * @param array<int, bool> $ambiguous token indices that are a mismatched closer with no parent level
     */
    public function __construct(
        public array $ambiguous,
        public bool $hadSurplus,
    ) {}
}
