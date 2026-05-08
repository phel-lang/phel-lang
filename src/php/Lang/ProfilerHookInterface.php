<?php

declare(strict_types=1);

namespace Phel\Lang;

interface ProfilerHookInterface
{
    /**
     * Wrap a freshly defined `AbstractFn` so future calls go through the
     * profiler. Returning `$fn` unwrapped is acceptable when this fn
     * should not be profiled (e.g. anonymous/inline closures).
     */
    public function wrapFn(AbstractFn $fn): AbstractFn;

    public function recordPhase(string $phase, string $source, float $elapsedMs): void;
}
