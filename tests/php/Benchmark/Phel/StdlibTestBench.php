<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Override;
use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * What recording a test result costs, which nothing measured before.
 *
 * `phel test` calls `report` once per assertion, and a profile of a real
 * suite put two thirds of a run's time inside that call rather than inside
 * the assertion bodies. The expensive half is the stats bookkeeping: an
 * assertion event updates counters on the internal atom, a lifecycle event
 * does not.
 *
 * That difference is the pair this bench is built on, in the spirit of the
 * `bench_x` / `bench_x_raw` convention {@see CoreBenchCase} describes:
 *
 * - `bench_report_pass` reports a passing assertion, counters and all;
 * - `bench_report_lifecycle` reports `:begin-test`, which flows straight
 *   through to the reporter set without touching the atom.
 *
 * The reviewable number is the gap between them, which is the per-assertion
 * bookkeeping cost. Reporters are cleared in `setUpFixtures`, so neither
 * subject measures a dot printer writing to stdout.
 *
 * @BeforeMethods("setUp")
 */
final class StdlibTestBench extends CoreBenchCase
{
    /** @var callable */
    private $report;

    private mixed $passEvent = null;

    private mixed $lifecycleEvent = null;

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_report_pass(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->report)($this->passEvent);
        }
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(10)
     */
    public function bench_report_lifecycle(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->report)($this->lifecycleEvent);
        }
    }

    /**
     * @return list<string>
     */
    #[Override]
    protected function extraNamespaces(): array
    {
        return ['phel.test'];
    }

    #[Override]
    protected function setUpFixtures(): void
    {
        $this->report = $this->phelFn('phel.test', 'report');
        ($this->phelFn('phel.test', 'clear-reporters!'))();

        $keyword = Phel::keyword(...);

        $this->passEvent = Phel::map(
            $keyword('type'),
            $keyword('pass'),
            $keyword('message'),
            'bench assertion',
        );

        $this->lifecycleEvent = Phel::map(
            $keyword('type'),
            $keyword('begin-test'),
            $keyword('test-name'),
            'bench-test',
        );
    }
}
