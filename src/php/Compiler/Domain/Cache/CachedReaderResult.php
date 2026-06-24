<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Cache;

use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;

/**
 * A cached reader result plus the number of gensyms its read phase consumed.
 *
 * The delta is replayed on a warm rebuild so the analyzer and emitter draw the
 * same gensym counter values the skipped read phase would have, keeping a
 * file's emitted PHP stable across replays from the same counter trajectory —
 * including source that uses quasiquote auto-gensym (`x#`) or the short-fn
 * reader (`|(...)`). Gensym names are process-global, so a build that mixes
 * cold compiles with compiled-code-cache hits may renumber them; that is a
 * pre-existing limitation, independent of this cache.
 */
final readonly class CachedReaderResult
{
    public function __construct(
        public ReaderResult $readerResult,
        public int $gensymDelta,
    ) {}
}
