<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler\Fixtures;

use Phel\Lang\Keyword;

/**
 * Hypothetical hoist: a single per-ns cache shared across fns of the
 * same ns. Models the parent-class static-array variant of issue #2130
 * (`AbstractFn::$kwCache[$ns][$slot] ??= Keyword::create(...)`). The
 * `$ns` key is constant-folded into the path to mirror what the emitter
 * could realistically produce.
 */
final class KeywordEmitNsScope
{
    /** @var array<string, Keyword> */
    public static array $cache = [];

    /**
     * @return list<Keyword>
     */
    public function __invoke(): array
    {
        return [
            self::$cache['alpha'] ??= Keyword::create('alpha'),
            self::$cache['beta'] ??= Keyword::create('beta'),
            self::$cache['gamma'] ??= Keyword::create('gamma'),
        ];
    }
}
