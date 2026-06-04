<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

use Phel\Lang\AbstractFn;
use Phel\Lang\ProfilerHookInterface;

use function array_pop;
use function hrtime;

final class ProfilerSession implements ProfilerHookInterface
{
    /**
     * Positional tuple per frame: [name, enterNs, childInclusiveNs]. Keyed
     * arrays would allocate a hash slot per call; the positional layout
     * keeps `enter` to a single push on every fn invocation.
     *
     * @var list<array{0:string, 1:int, 2:int}>
     */
    private array $stack = [];

    /** @var array<string, array{calls:int, totalNs:int, selfNs:int, maxNs:int}> */
    private array $fnStats = [];

    /** @var array<string, array<string, float>> */
    private array $phaseMs = [];

    private int $depth = 0;

    private readonly int $startedAtNs;

    public function __construct()
    {
        $this->startedAtNs = hrtime(true);
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

        return new ProfilingFn($fn, $this);
    }

    /**
     * Push a call frame onto the stack. Must be paired with `exit()`.
     *
     * @param string $name Fully-qualified fn name (the proxy's `BOUND_TO` value)
     */
    public function enter(string $name): void
    {
        $this->stack[] = [$name, hrtime(true), 0];
        ++$this->depth;
    }

    /**
     * Pop the current frame and fold its inclusive time into the parent's
     * child total (so the parent's self-time excludes this call). An unmatched
     * exit on an empty stack is silently ignored.
     */
    public function exit(): void
    {
        $frame = array_pop($this->stack);
        if ($frame === null) {
            return;
        }

        --$this->depth;
        $inclusive = hrtime(true) - $frame[1];
        $this->recordCall($frame[0], $inclusive, $inclusive - $frame[2]);

        if ($this->depth > 0) {
            /** @psalm-suppress PropertyTypeCoercion */
            $this->stack[$this->depth - 1][2] += $inclusive;
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

    private function recordCall(string $name, int $inclusive, int $self): void
    {
        // Bind a reference to the bucket so the four hot-path mutations
        // touch a single hash slot instead of re-resolving `$name` each
        // time. Tight inner loops compound these lookups quickly.
        if (!isset($this->fnStats[$name])) {
            $this->fnStats[$name] = ['calls' => 0, 'totalNs' => 0, 'selfNs' => 0, 'maxNs' => 0];
        }

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
