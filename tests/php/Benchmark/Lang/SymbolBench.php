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

    public function setUp(): void
    {
        $this->symbol = Symbol::createForNamespace('phel\\core', 'map-indexed');
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
}
