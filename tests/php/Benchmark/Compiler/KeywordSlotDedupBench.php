<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel;
use Phel\Compiler\CompilerFacade;
use Phel\Shared\CompileOptions;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Warmup;

use function implode;
use function str_repeat;

/**
 * Compile-path benchmark for the constant-slot dedup (issue #2564).
 *
 * Compiles a fn body that repeats the *same* keyword many times. Before
 * the dedup, each occurrence reserved its own `$__phel_const_N` slot (so N
 * `??=` guards, N `use (&...)` capture entries, N `static` declarations);
 * after the dedup all occurrences of the same keyword collapse to one slot.
 *
 * `bench_compile_repeated_keyword` stresses the collapsing path (one
 * keyword repeated `OCCURRENCES` times); `bench_compile_distinct_keywords`
 * is the control (distinct keywords keep distinct slots, so emitter work is
 * unchanged). The delta between the two isolates the dedup bookkeeping.
 *
 * @BeforeMethods("setUp")
 */
final class KeywordSlotDedupBench
{
    private const int OCCURRENCES = 64;

    private CompilerFacade $compilerFacade;

    private string $repeatedKeywordSource = '';

    private string $distinctKeywordsSource = '';

    public function setUp(): void
    {
        Phel::bootstrap(__DIR__ . '/../../../../');

        $this->compilerFacade = new CompilerFacade();
        $this->repeatedKeywordSource = $this->buildRepeatedKeywordSource();
        $this->distinctKeywordsSource = $this->buildDistinctKeywordsSource();
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_compile_repeated_keyword(): void
    {
        $this->compilerFacade->compile($this->repeatedKeywordSource, new CompileOptions());
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     *
     * @Warmup(1)
     */
    public function bench_compile_distinct_keywords(): void
    {
        $this->compilerFacade->compile($this->distinctKeywordsSource, new CompileOptions());
    }

    private function buildRepeatedKeywordSource(): string
    {
        // (fn [m] [(:k m) (:k m) ...]) — same keyword OCCURRENCES times.
        $accessor = '(:k m)';
        $body = str_repeat($accessor . ' ', self::OCCURRENCES);

        return '(fn [m] [' . $body . '])';
    }

    private function buildDistinctKeywordsSource(): string
    {
        // (fn [m] [(:k0 m) (:k1 m) ...]) — distinct keywords, no dedup.
        $accessors = [];
        for ($i = 0; $i < self::OCCURRENCES; ++$i) {
            $accessors[] = '(:k' . $i . ' m)';
        }

        return '(fn [m] [' . implode(' ', $accessors) . '])';
    }
}
