<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use Phel\Lang\Keyword;
use Phel\Run\RunFacade;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function is_callable;
use function sprintf;

/**
 * A multi-key `assoc` written out at the call site, which the emitter lowers
 * to one three-argument step per pair rather than the variadic arity (#3317).
 * A multi-key `assoc!` on a transient is lowered the same way (#3318); its
 * subjects open and close the transient too, the round trip a caller pays.
 *
 * The lowering lives in the emitted code, not in `phel.core/assoc`, so each
 * subject compiles a Phel `fn` around the call once, in `setUpFixtures`, and
 * measures calling it, as {@see CoreMacroBench} does for `for`. The target is
 * a 90-key hash map, the shape the issue measured.
 *
 * {@see CoreBenchCase} for the conventions every subject here follows.
 *
 * @BeforeMethods("setUp")
 */
final class CoreAssocPairsBench extends CoreBenchCase
{
    private const int MAP_SIZE = 90;

    private const int PAIRS = 20;

    /** @var callable */
    private $assocThree;

    /** @var callable */
    private $assocTwenty;

    /** @var callable */
    private $assocBangTwenty;

    private mixed $map = null;

    /** @var list<Keyword> */
    private array $keys = [];

    /**
     * @Revs(1000)
     */
    public function bench_assoc_three_pairs(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->assocThree)($this->map);
        }
    }

    /**
     * The floor: the three `put` calls the pairs come down to.
     *
     * @Revs(1000)
     */
    public function bench_assoc_three_pairs_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            (void) $this->map->put($this->keys[0], 0)->put($this->keys[1], 1)->put($this->keys[2], 2);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_twenty_pairs(): void
    {
        ($this->assocTwenty)($this->map);
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_twenty_pairs_raw(): void
    {
        $map = $this->map;
        for ($i = 0; $i < self::PAIRS; ++$i) {
            $map = $map->put($this->keys[$i], $i);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_bang_twenty_pairs(): void
    {
        ($this->assocBangTwenty)($this->map);
    }

    /**
     * @Revs(1000)
     */
    public function bench_assoc_bang_twenty_pairs_raw(): void
    {
        $transient = $this->map->asTransient();
        for ($i = 0; $i < self::PAIRS; ++$i) {
            $transient->put($this->keys[$i], $i);
        }

        (void) $transient->persistent();
    }

    protected function setUpFixtures(): void
    {
        $entries = [];
        for ($i = 0; $i < self::MAP_SIZE; ++$i) {
            $entries[] = Keyword::create('k' . $i);
            $entries[] = $i;
        }

        $this->map = Phel::map(...$entries);

        $pairs = '';
        for ($i = 0; $i < self::PAIRS; ++$i) {
            $this->keys[] = Keyword::create('k' . $i);
            $pairs .= sprintf(' :k%d %d', $i, $i);
        }

        $this->assocThree = $this->compileFn('(fn [m] (assoc m :k0 0 :k1 1 :k2 2))');
        $this->assocTwenty = $this->compileFn(sprintf('(fn [m] (assoc m%s))', $pairs));
        $this->assocBangTwenty = $this->compileFn(sprintf('(fn [m] (persistent! (assoc! (transient m)%s)))', $pairs));
    }

    private function compileFn(string $phelCode): callable
    {
        $fn = new RunFacade()->eval($phelCode);
        if (!is_callable($fn)) {
            throw new RuntimeException(sprintf('%s did not evaluate to a callable', $phelCode));
        }

        return $fn;
    }
}
