<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Override;
use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * `phel.walk`, which had no benchmark at all.
 *
 * `keywordize-keys` and `stringify-keys` are the interop-boundary pair: both
 * run over decoded JSON or an HTTP payload, so they see a whole tree rather
 * than one collection. `postwalk` with `identity` is the floor underneath
 * them, the traversal with no transformation of its own.
 *
 * The fixture is deliberately a tree and not a flat map: the map rebuild these
 * measure runs once per map in the structure, so a flat input would hide most
 * of the cost.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class StdlibWalkBench extends CoreBenchCase
{
    private const int WIDTH = 16;

    /** @var callable */
    private $keywordizeKeys;

    /** @var callable */
    private $stringifyKeys;

    /** @var callable */
    private $postwalk;

    /** @var callable */
    private $identity;

    private mixed $stringKeyed = null;

    private mixed $keywordKeyed = null;

    /**
     * @Revs(1000)
     */
    public function bench_keywordize_keys(): void
    {
        ($this->keywordizeKeys)($this->stringKeyed);
    }

    /**
     * @Revs(1000)
     */
    public function bench_stringify_keys(): void
    {
        ($this->stringifyKeys)($this->keywordKeyed);
    }

    /**
     * The traversal on its own, with nothing to rewrite.
     *
     * @Revs(1000)
     */
    public function bench_postwalk_identity(): void
    {
        ($this->postwalk)($this->identity, $this->stringKeyed);
    }

    #[Override]
    protected function extraNamespaces(): array
    {
        return ['phel.walk'];
    }

    protected function setUpFixtures(): void
    {
        $this->keywordizeKeys = $this->phelFn('phel.walk', 'keywordize-keys');
        $this->stringifyKeys = $this->phelFn('phel.walk', 'stringify-keys');
        $this->postwalk = $this->phelFn('phel.walk', 'postwalk');
        $this->identity = $this->coreFn('identity');

        $inner = [];
        for ($i = 0; $i < self::WIDTH; ++$i) {
            $inner[] = 'k' . $i;
            $inner[] = $i;
        }

        $rows = [];
        for ($i = 0; $i < self::WIDTH; ++$i) {
            $rows[] = Phel::map('x', $i, 'y', (string) $i);
        }

        $this->stringKeyed = Phel::map(
            'a',
            Phel::map(...$inner),
            'b',
            Phel::vector($rows),
            'c',
            'leaf',
        );

        $this->keywordKeyed = ($this->keywordizeKeys)($this->stringKeyed);
    }
}
