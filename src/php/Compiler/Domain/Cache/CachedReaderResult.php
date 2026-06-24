<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Cache;

use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;

/**
 * A cached reader result plus the number of gensyms its read phase consumed.
 *
 * The delta is replayed on a warm rebuild so the analyzer and emitter draw the
 * same gensym counter values they would in a cold compile, keeping the emitted
 * PHP byte-identical even when the source uses quasiquote auto-gensym (`x#`) or
 * the short-fn reader (`|(...)`).
 */
final readonly class CachedReaderResult
{
    public function __construct(
        public ReaderResult $readerResult,
        public int $gensymDelta,
    ) {}
}
