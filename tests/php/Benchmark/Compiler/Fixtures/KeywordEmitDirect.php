<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel\Lang\Keyword;

/**
 * No per-call-site cache. Models the bypass-facade alternative (#2131):
 * `LiteralEmitter::emitKeyword` already emits `Keyword::create(...)`
 * directly, so this is what the call site degenerates to if the per-fn
 * cache is removed. `Keyword::create` itself still hits the intern pool
 * via `self::$internPool[$key] ??= new self(...)`.
 */
final class KeywordEmitDirect
{
    /**
     * @return list<Keyword>
     */
    public function __invoke(): array
    {
        return [
            Keyword::create('alpha'),
            Keyword::create('beta'),
            Keyword::create('gamma'),
        ];
    }
}
