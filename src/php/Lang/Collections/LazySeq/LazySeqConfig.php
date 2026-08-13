<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

/**
 * Configuration constants for lazy sequence behavior.
 */
final class LazySeqConfig
{
    /**
     * Size of chunks when using chunked sequences.
     * Chunked sequences realize elements in batches for better performance.
     */
    public const int CHUNK_SIZE = 32;

    /**
     * Size of the *first* chunk a lazy sequence realizes.
     *
     * `ChunkedSeq::fromGenerator` answers "is this sequence empty?" by
     * returning null, which means construction has to pull at least one
     * element. It does not have to pull {@see self::CHUNK_SIZE} of them, and
     * doing so made construction cost proportional to the whole collection for
     * anything up to 32 elements, whether or not the caller ever looked past
     * the first (#3061).
     *
     * Every later chunk is a full `CHUNK_SIZE`, so a sequence that is actually
     * consumed pays one extra chunk boundary and nothing more, while one that is
     * abandoned after a couple of elements never realizes 32.
     */
    public const int FIRST_CHUNK_SIZE = 4;

    /**
     * Maximum number of elements to display when printing a lazy sequence in the REPL.
     * This prevents accidentally realizing huge or infinite sequences.
     */
    public const int REPL_DISPLAY_LIMIT = 100;

    private function __construct()
    {
        // Prevent instantiation
    }
}
