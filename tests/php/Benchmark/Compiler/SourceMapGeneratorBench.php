<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapGenerator;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `SourceMapGenerator::encode()` micro-benchmark.
 *
 * Compiling a real namespace emits thousands of mappings; this builds a
 * representative dense mapping set so the serialization buffer path is
 * measured in isolation.
 */
final class SourceMapGeneratorBench
{
    private const int MAPPINGS = 4000;

    /** @var list<array{generated: array{line: int, column: int}, original: array{line: int, column: int}}> */
    private array $mappings = [];

    public function setUp(): void
    {
        $mappings = [];
        for ($i = 0; $i < self::MAPPINGS; ++$i) {
            $line = intdiv($i, 8);
            $column = ($i % 8) * 4;
            $mappings[] = [
                'generated' => ['line' => $line, 'column' => $column],
                'original' => ['line' => $line, 'column' => $column + 1],
            ];
        }

        $this->mappings = $mappings;
    }

    /**
     * @Revs(2000)
     *
     * @Iterations(5)
     *
     * @BeforeMethods("setUp")
     */
    public function bench_encode(): void
    {
        new SourceMapGenerator()->encode($this->mappings);
    }
}
