<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\LazySeq;

/**
 * Configuration constants for lazy sequence behavior.
 */
final class LazySeqConfig
{
    /**
     * Collections with fewer elements than this threshold will be realized eagerly.
     * This optimizes performance for small collections where lazy evaluation overhead
     * would be greater than the benefit.
     */
    public const int EAGER_THRESHOLD = 32;

    /**
     * Size of chunks when using chunked sequences.
     * Chunked sequences realize elements in batches for better performance.
     */
    public const int CHUNK_SIZE = 32;

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
