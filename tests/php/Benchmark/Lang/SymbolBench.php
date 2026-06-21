<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Lang;

use Phel\Lang\Symbol;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `Symbol::hash()` micro-benchmark.
 *
 * The compiler hashes the same `Symbol` instance many times while
 * resolving it against the global, local, and shadowed environments
 * (each backed by a hash map). This measures repeated hashing of a
 * single instance, which is exactly that pattern.
 */
final class SymbolBench
{
    private Symbol $symbol;

    private Symbol $valueEqual;

    public function setUp(): void
    {
        $this->symbol = Symbol::createForNamespace('phel\\core', 'map-indexed');
        $this->valueEqual = Symbol::createForNamespace('phel\\core', 'map-indexed');
    }

    /**
     * @Revs(100000)
     *
     * @Iterations(5)
     *
     * @BeforeMethods("setUp")
     */
    public function bench_repeated_hash(): void
    {
        $symbol = $this->symbol;
        for ($i = 0; $i < 16; ++$i) {
            $symbol->hash();
        }
    }

    /**
     * `Symbol::equals()` micro-benchmark.
     *
     * Environment lookups compare a symbol against itself (identity fast
     * path) and against distinct, value-equal instances (field comparison).
     * Both branches are exercised here.
     *
     * @Revs(100000)
     *
     * @Iterations(5)
     *
     * @BeforeMethods("setUp")
     */
    public function bench_equals(): void
    {
        $symbol = $this->symbol;
        $valueEqual = $this->valueEqual;
        for ($i = 0; $i < 16; ++$i) {
            $symbol->equals($symbol);
            $symbol->equals($valueEqual);
        }
    }
}
