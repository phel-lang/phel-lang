<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Cache;

/**
 * Caches the front-half compiler output (lex -> parse -> read) for a whole
 * source string, so a warm rebuild can skip straight to analysis + emission.
 *
 * Reading is effectively source-deterministic (macro expansion happens later,
 * in the analyzer), so a read result is valid for the same source regardless of
 * which macros are in scope. This is what makes it safe to cache by source hash
 * alone, unlike the analyzed AST.
 *
 * The reader does consult the global environment in one spot — a quasiquoted
 * bare symbol is rewritten to its fully-qualified `namespace/name`
 * (`QuasiquoteTransformer`). That rewrite encodes only the resolved *namespace
 * identity*, which is fixed by the file's own `(ns ...)` refers/aliases (its
 * source) plus the always-present `phel.core`; a dependency changing an
 * export's value never changes which namespace a symbol resolves to. So the
 * source hash remains a complete key. The one assumption is that user reader
 * tag handlers (`set-tag`) are deterministic.
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
