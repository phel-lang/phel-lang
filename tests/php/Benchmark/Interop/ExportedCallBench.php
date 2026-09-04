<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Interop;

use Closure;
use Phel\Lang\Registry;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * What a PHP host pays per call into Phel.
 *
 * An application that keeps its business logic in Phel, or that calls a few
 * exported functions from an existing PHP codebase, reaches Phel through
 * `PhelCallerTrait::callPhel()` and nothing else: `phel export` writes one
 * wrapper method per function and every one of them is that call. Nothing in
 * the suite timed it. `GlobalVarReadBench` times the `\Phel::getDefinition()`
 * underneath, and the `phel.core` subjects time functions reached from Phel
 * rather than from PHP.
 *
 * The reviewable number is the ratio to `bench_direct_call`, which is the same
 * function invoked without the wrapper. That gap is what the boundary costs:
 * the cache key concatenation, the static cache lookup and the variadic
 * spread. It is the number to defend when someone proposes making resolution
 * smarter, and the one that says whether "call Phel from PHP" is affordable
 * per request.
 *
 * Process boot is deliberately not a subject here. `Phel::bootstrap()`
 * memoizes, so a second call in the same process returns in about 0.25ms
 * against roughly 5ms for the first, and the benchmark job runs with
 * `--warmup=2`, which would hand every measured revolution the memoized path.
 * The load term that dominates a cold host is gated instead by
 * {@see \PhelTest\Benchmark\Run\ReplBootBench}, which times namespace closure
 * evaluation and re-enters cleanly.
 *
 * Revs are looped internally so timer overhead is amortised rather than
 * measured, matching {@see \PhelTest\Benchmark\Lang\GlobalVarReadBench}.
 *
 * @BeforeMethods("setUp")
 */
final class ExportedCallBench
{
    private const int INNER = 32;

    /** Registry keys are munged, so the wrapper's `bench-embed.host` lands here. */
    private const string REGISTRY_NS = 'bench_embed.host';

    private const string NAME = 'identity';

    private Closure $definition;

    public function setUp(): void
    {
        $this->definition = static fn(mixed $value): mixed => $value;

        Registry::getInstance()->addDefinition(self::REGISTRY_NS, self::NAME, $this->definition);
    }

    /**
     * A PHP host calling an exported function, steady state.
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_exported_call(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ExportedWrapperFixture::identity($i);
        }
    }

    /**
     * The same function without the wrapper: the floor the ratio is against.
     *
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_direct_call(): void
    {
        $fn = $this->definition;

        for ($i = 0; $i < self::INNER; ++$i) {
            $fn($i);
        }
    }
}
