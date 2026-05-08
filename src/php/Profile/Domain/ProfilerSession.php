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
        $this->stack[] = ['name' => $name, 'enter' => hrtime(true), 'sub' => 0];
    }

    public function exit(): void
    {
        $frame = array_pop($this->stack);
        if ($frame === null) {
            return;
        }

        $inclusive = hrtime(true) - $frame['enter'];
        $this->recordCall($frame['name'], $inclusive, $inclusive - $frame['sub']);

        $depth = count($this->stack);
        if ($depth > 0) {
            /** @psalm-suppress PropertyTypeCoercion */
            $this->stack[$depth - 1]['sub'] += $inclusive;
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
        if (!isset($this->fnStats[$name])) {
            $this->fnStats[$name] = ['calls' => 0, 'totalNs' => 0, 'selfNs' => 0, 'maxNs' => 0];
        }

        ++$this->fnStats[$name]['calls'];
        $this->fnStats[$name]['totalNs'] += $inclusive;
        $this->fnStats[$name]['selfNs'] += $self;
        if ($inclusive > $this->fnStats[$name]['maxNs']) {
            $this->fnStats[$name]['maxNs'] = $inclusive;
        }
    }
}
