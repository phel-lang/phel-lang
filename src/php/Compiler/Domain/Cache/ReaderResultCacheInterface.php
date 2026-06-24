<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Cache;

/**
 * Caches the front-half compiler output (lex -> parse -> read) for a whole
 * source string, so a warm rebuild can skip straight to analysis + emission.
 *
 * Reading is purely syntactic and runtime-independent (macro expansion happens
 * later, in the analyzer), so a read result is always valid for the same source
 * regardless of which macros are in scope. This is what makes it safe to cache
 * by source hash alone, unlike the analyzed AST.
 */
interface ReaderResultCacheInterface
{
    /**
     * @return list<CachedReaderResult>|null the cached forms, or null on a miss
     */
    public function load(string $phelCode, int $optimizationLevel): ?array;

    /**
     * @param list<CachedReaderResult> $entries
     */
    public function save(string $phelCode, int $optimizationLevel, array $entries): void;
}
