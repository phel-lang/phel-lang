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

    public function wrapFn(AbstractFn $fn): ProfilingFn
    {
        if ($fn instanceof ProfilingFn) {
            return $fn;
        }

        return new ProfilingFn($fn, $this);
    }

    public function enter(string $name): void
    {
        $this->stack[] = [$name, hrtime(true), 0];
        ++$this->depth;
    }

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

    public function recordPhase(string $phase, string $source, float $elapsedMs): void
    {
        $this->phaseMs[$source][$phase] = ($this->phaseMs[$source][$phase] ?? 0.0) + $elapsedMs;
    }

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
