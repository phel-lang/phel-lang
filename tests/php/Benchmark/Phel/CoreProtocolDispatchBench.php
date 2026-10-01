<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use ArrayObject;
use Phel\Run\RunFacade;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;
use SplObjectStorage;

use function is_array;
use function is_callable;

/**
 * Protocol dispatch on a PHP object, measured through the generated protocol
 * fn against a direct call to the implementation it selects.
 *
 * The exact-class subject is the hot path: one lookup on the dispatch table
 * and a call. The parent and interface walk runs only on a miss, so a
 * regression there shows up as a gap between `bench_exact_class` and
 * `bench_exact_class_raw`. `bench_interface` covers the memoized walk.
 *
 * @BeforeMethods("setUp")
 */
final class CoreProtocolDispatchBench extends CoreBenchCase
{
    /** @var callable */
    private $label;

    /** @var callable */
    private $exactImpl;

    private ArrayObject $arrayObject;

    private SplObjectStorage $storage;

    /**
     * @Revs(1000)
     */
    public function bench_exact_class(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->label)($this->arrayObject);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_exact_class_raw(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->exactImpl)($this->arrayObject);
        }
    }

    /**
     * @Revs(1000)
     */
    public function bench_interface(): void
    {
        for ($i = 0; $i < self::INNER; ++$i) {
            ($this->label)($this->storage);
        }
    }

    protected function setUpFixtures(): void
    {
        $fixture = new RunFacade()->eval(<<<'PHEL'
            (do
              (defprotocol BenchLabel (bench-label [x]))
              (extend-type \ArrayObject BenchLabel (bench-label [x] :array-object))
              (extend-type \Countable BenchLabel (bench-label [x] :countable))
              (php/array bench-label (get (deref BenchLabel--bench-label--dispatch) "ArrayObject")))
            PHEL);

        if (!is_array($fixture) || !is_callable($fixture[0]) || !is_callable($fixture[1])) {
            throw new RuntimeException('The protocol fixture did not evaluate to two callables');
        }

        $this->label = $fixture[0];
        $this->exactImpl = $fixture[1];
        $this->arrayObject = new ArrayObject();
        $this->storage = new SplObjectStorage();
    }
}
