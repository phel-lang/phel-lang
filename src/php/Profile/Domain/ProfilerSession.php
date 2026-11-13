<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

use Fiber;
use Phel\Lang\AbstractFn;
use Phel\Lang\ProfilerHookInterface;
use WeakMap;

use function array_pop;
use function count;
use function hrtime;

/**
 * @phpstan-import-type FnStat from ProfileReport
 *
 * @internal
 */
final class ProfilerSession implements ProfilerHookInterface
{
    private readonly CallStack $mainStack;

    /**
     * One stack per fiber: fibers interleave, so a shared stack would pop
     * another fiber's frame when one of them returns.
     *
     * @var WeakMap<object, CallStack>
     */
    private WeakMap $fiberStacks;

    /** @var array<string, FnStat> */
    private array $fnStats = [];

    /** @var array<string, array<string, float>> */
    private array $phaseMs = [];

    private readonly int $startedAtNs;

    public function __construct()
    {
        $this->startedAtNs = hrtime(true);
        $this->mainStack = new CallStack();
        /** @var WeakMap<object, CallStack> $fiberStacks */
        $fiberStacks = new WeakMap();
        $this->fiberStacks = $fiberStacks;
    }

    /**
     * Wrap a fn in a {@see ProfilingFn} proxy. Idempotent: an already-wrapped
     * fn is returned unchanged so the registry hook never double-wraps.
     */
    public function wrapFn(AbstractFn $fn): ProfilingFn
    {
        if ($fn instanceof ProfilingFn) {
            return $fn;
        }

        return new ProfilingFn($fn, $this)->withMeta($fn->getMeta());
    }

    /**
     * Push a call frame onto the stack. Must be paired with `exit()`.
     *
     * @param string $name Fully-qualified fn name (the proxy's `BOUND_TO` value)
     */
    public function enter(string $name): void
    {
        $this->currentStack()->frames[] = [$name, (int) hrtime(true), 0];
    }

    /**
     * Pop the current frame and fold its inclusive time into the parent's
     * child total (so the parent's self-time excludes this call). An unmatched
     * exit on an empty stack is silently ignored.
     */
    public function exit(): void
    {
        $stack = $this->currentStack();
        $frame = array_pop($stack->frames);
        if ($frame === null) {
            return;
        }

        $inclusive = hrtime(true) - $frame[1];
        $this->recordCall($frame[0], $inclusive, $inclusive - $frame[2]);

        $depth = count($stack->frames);
        if ($depth > 0) {
            /** @psalm-suppress PropertyTypeCoercion */
            $stack->frames[$depth - 1][2] += $inclusive;
        }
    }

    /**
     * Record elapsed time for one compile phase of one source. Accumulates:
     * repeated calls for the same source/phase pair add up.
     *
     * @param string $phase     Compile phase name (e.g. lex, parse, read, analyze, emit)
     * @param string $source    File path or namespace being compiled
     * @param float  $elapsedMs Elapsed milliseconds to add to the running total
     */
    public function recordPhase(string $phase, string $source, float $elapsedMs): void
    {
        $this->phaseMs[$source][$phase] = ($this->phaseMs[$source][$phase] ?? 0.0) + $elapsedMs;
    }

    /**
     * Finalize the session and snapshot the collected stats into an immutable
     * report. Intended to be called once after the profiled run completes.
     */
    public function stop(): ProfileReport
    {
        return new ProfileReport(
            $this->fnStats,
            $this->phaseMs,
            (hrtime(true) - $this->startedAtNs) / 1_000_000,
        );
    }

    private function currentStack(): CallStack
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            return $this->mainStack;
        }

        $stack = $this->fiberStacks[$fiber] ?? null;
        if (!$stack instanceof CallStack) {
            $stack = new CallStack();
            $this->fiberStacks[$fiber] = $stack;
        }

        return $stack;
    }

    private function recordCall(string $name, int $inclusive, int $self): void
    {
        // Bind a reference to the bucket so the four hot-path mutations
        // touch a single hash slot instead of re-resolving `$name` each
        // time. Tight inner loops compound these lookups quickly.
        $this->fnStats[$name] ??= ['calls' => 0, 'totalNs' => 0, 'selfNs' => 0, 'maxNs' => 0];

        /** @psalm-suppress UnsupportedPropertyReferenceUsage */
        $stat = &$this->fnStats[$name];
        ++$stat['calls'];
        $stat['totalNs'] += $inclusive;
        $stat['selfNs'] += $self;
        if ($inclusive > $stat['maxNs']) {
            $stat['maxNs'] = $inclusive;
        }
    }
}
