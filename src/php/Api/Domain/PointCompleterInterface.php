<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Shared\Api\Completion;
use Phel\Shared\Api\ProjectIndex;

/**
 * Ranks completion candidates for a cursor position.
 *
 * Implementations are best-effort: the result is a suggestion list, not a
 * complete symbol table, and an empty list means "nothing to suggest", never
 * "nothing is in scope". Implementations must not throw on a source buffer
 * that fails to parse.
 *
 * @internal
 */
interface PointCompleterInterface
{
    /**
     * @return list<Completion> possibly incomplete; never throws
     */
    public function completeAtPoint(string $source, int $line, int $col, ProjectIndex $index): array;
}
