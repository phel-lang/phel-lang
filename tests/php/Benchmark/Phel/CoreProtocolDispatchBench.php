<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use ArrayObject;
use Phel\Run\RunFacade;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

use function is_array;
use function is_callable;

/**
 * Protocol dispatch on a PHP object, measured through the generated protocol
 * fn against a direct call to the implementation it selects.
 *
 * The exact-class subject is the hot path: one lookup on the dispatch table
 * and a call. The parent and interface walk runs only on a miss, so a
 * regression that leaks it into the hit path shows up as a gap between
 * `bench_exact_class` and `bench_exact_class_raw`. The type is registered
 * with the string key, the one spelling that registers the same key before
 * #3413, so the CI baseline run on the previous commit measures it too.
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

    protected function setUpFixtures(): void
    {
        $fixture = new RunFacade()->eval(<<<'PHEL'
            (do
              (defprotocol BenchLabel (bench-label [x]))
              (extend-type "ArrayObject" BenchLabel (bench-label [x] :array-object))
              (php/array bench-label (get (deref BenchLabel--bench-label--dispatch) "ArrayObject")))
            PHEL);

        if (!is_array($fixture) || !is_callable($fixture[0]) || !is_callable($fixture[1])) {
            throw new RuntimeException('The protocol fixture did not evaluate to two callables');
        }

        $this->label = $fixture[0];
        $this->exactImpl = $fixture[1];
        $this->arrayObject = new ArrayObject();
    }
}
