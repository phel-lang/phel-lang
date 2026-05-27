<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel\Lang\Keyword;

/**
 * Mirrors the current emit (per-fn `static $__phel_kw_N` slot reserved by
 * `BodyConstantScanner`). After warm-up each `??=` short-circuits to the
 * cached slot, so steady-state cost is one static read per keyword + the
 * array build.
 */
final class KeywordEmitStatusQuo
{
    /**
     * @return list<Keyword>
     */
    public function __invoke(): array
    {
        static $kw0;
        static $kw1;
        static $kw2;
        $kw0 ??= Keyword::create('alpha');
        $kw1 ??= Keyword::create('beta');
        $kw2 ??= Keyword::create('gamma');

        return [$kw0, $kw1, $kw2];
    }
}
