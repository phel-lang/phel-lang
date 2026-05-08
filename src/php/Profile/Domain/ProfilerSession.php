<?php

declare(strict_types=1);

namespace Phel\Profile\Domain;

use Phel\Lang\AbstractFn;
use Phel\Lang\ProfilerHookInterface;

use function array_pop;
use function count;
use function hrtime;

final class ProfilerSession implements ProfilerHookInterface
{
    /** @var list<array{name:string, enter:int, sub:int}> */
    private array $stack = [];

    /** @var array<string, array{calls:int, totalNs:int, selfNs:int, maxNs:int}> */
    private array $fnStats = [];

    /** @var array<string, array<string, float>> */
    private array $phaseMs = [];

    private readonly int $startedAtNs;

    private int $stoppedAtNs = 0;

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

    public function enter(string $name): int
    {
        $now = hrtime(true);
        $this->stack[] = ['name' => $name, 'enter' => $now, 'sub' => 0];

        return $now;
    }

    public function exit(string $name, int $startNs): void
    {
        $now = hrtime(true);
        $frame = array_pop($this->stack);
        if ($frame === null) {
            return;
        }

        $inclusive = $now - $frame['enter'];
        $self = $inclusive - $frame['sub'];

        if (!isset($this->fnStats[$name])) {
            $this->fnStats[$name] = ['calls' => 0, 'totalNs' => 0, 'selfNs' => 0, 'maxNs' => 0];
        }

        ++$this->fnStats[$name]['calls'];
        $this->fnStats[$name]['totalNs'] += $inclusive;
        $this->fnStats[$name]['selfNs'] += $self;
        if ($inclusive > $this->fnStats[$name]['maxNs']) {
            $this->fnStats[$name]['maxNs'] = $inclusive;
        }

        $depth = count($this->stack);
        if ($depth > 0) {
            /** @psalm-suppress PropertyTypeCoercion */
            $this->stack[$depth - 1]['sub'] += $inclusive;
        }
    }

    public function recordPhase(string $phase, string $source, float $elapsedMs): void
    {
        if (!isset($this->phaseMs[$source])) {
            $this->phaseMs[$source] = [];
        }

        $current = $this->phaseMs[$source][$phase] ?? 0.0;
        $this->phaseMs[$source][$phase] = $current + $elapsedMs;
    }

    public function stop(): ProfileReport
    {
        $this->stoppedAtNs = hrtime(true);

        return new ProfileReport(
            $this->fnStats,
            $this->phaseMs,
            ($this->stoppedAtNs - $this->startedAtNs) / 1_000_000,
        );
    }
}
