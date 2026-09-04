<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Override;
use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `phel.pprint`, which had no benchmark at all.
 *
 * It is not only a REPL convenience: `inspect` and the failure diffs
 * `phel.test` prints both come through here, so it runs on the unhappy path
 * of every test run.
 *
 * Two shapes, because the layout code only runs on one of them. A form that
 * fits the width returns from the fast path at the top of `pp-str` without
 * ever laying anything out, so a fitting fixture would measure the printer
 * and nothing else. `bench_pprint_nested` is deliberately wider than the
 * 72-column default at every level; `bench_pprint_fits` guards the fast path
 * against a regression that would only show there.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class StdlibPprintBench extends CoreBenchCase
{
    private const int WIDTH = 12;

    /** @var callable */
    private $pprintStr;

    private mixed $nested = null;

    private mixed $small = null;

    /**
     * @Revs(100)
     */
    public function bench_pprint_nested(): void
    {
        ($this->pprintStr)($this->nested);
    }

    /**
     * @Revs(1000)
     */
    public function bench_pprint_fits(): void
    {
        ($this->pprintStr)($this->small);
    }

    /**
     * @return list<string>
     */
    #[Override]
    protected function extraNamespaces(): array
    {
        return ['phel.pprint'];
    }

    #[Override]
    protected function setUpFixtures(): void
    {
        $this->pprintStr = $this->phelFn('phel.pprint', 'pprint-str');

        $rows = [];
        for ($i = 0; $i < self::WIDTH; ++$i) {
            $cells = [];
            for ($j = 0; $j < self::WIDTH; ++$j) {
                $cells[] = Phel::keyword('cell-' . $j);
                $cells[] = 'value-' . $i . '-' . $j;
            }

            $rows[] = Phel::map(...$cells);
        }

        $this->nested = Phel::map(
            Phel::keyword('rows'),
            Phel::vector($rows),
            Phel::keyword('names'),
            Phel::list(['alpha', 'beta', 'gamma']),
        );

        $this->small = Phel::map(Phel::keyword('a'), 1, Phel::keyword('b'), 2);
    }
}
