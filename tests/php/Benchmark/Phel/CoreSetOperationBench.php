<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Phel;

use Phel;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * Call cost of the set algebra in `phel.core`, measured through the compiled
 * Phel function.
 *
 * These are the operations #2973 is about at their most common shape: two
 * sets. Each used to build a rest argument and `reduce` over it before doing
 * any set work at all, so the fixed two-set arity is what these subjects
 * guard.
 *
 * Unlike {@see CoreDispatchBench}, the subjects are not paired with a raw
 * equivalent: there is no one-line PHP that computes a persistent-set
 * intersection, so a pair would compare against a reimplementation rather
 * than against the operation. The cost already grows with the input, so each
 * subject runs once per rev instead of looping {@see CoreBenchCase::INNER}
 * times, following the rule the base class states for `sort` and `reduce`.
 *
 * @BeforeMethods("setUp")
 */
final class CoreSetOperationBench extends CoreBenchCase
{
    /** @var callable */
    private $intersection;

    /** @var callable */
    private $difference;

    /** @var callable */
    private $symmetricDifference;

    private mixed $left = null;

    private mixed $right = null;

    private mixed $third = null;

    /**
     * @Revs(1000)
     */
    public function bench_intersection_two(): void
    {
        ($this->intersection)($this->left, $this->right);
    }

    /**
     * The variadic tail, so a change that only moves the fixed arity is told
     * apart from one that moves both.
     *
     * @Revs(1000)
     */
    public function bench_intersection_three(): void
    {
        ($this->intersection)($this->left, $this->right, $this->third);
    }

    /**
     * @Revs(1000)
     */
    public function bench_difference_two(): void
    {
        ($this->difference)($this->left, $this->right);
    }

    /**
     * `symmetric-difference` pays the most: two differences and a union per
     * pair, and it used to allocate the pairing closure on every call.
     *
     * @Revs(1000)
     */
    public function bench_symmetric_difference_two(): void
    {
        ($this->symmetricDifference)($this->left, $this->right);
    }

    protected function setUpFixtures(): void
    {
        $this->intersection = $this->coreFn('intersection');
        $this->difference = $this->coreFn('difference');
        $this->symmetricDifference = $this->coreFn('symmetric-difference');

        // Overlapping halves, so neither operand short-circuits on size and
        // both the "smaller drives the walk" branches are exercised.
        $this->left = Phel::set(range(0, 15));
        $this->right = Phel::set(range(8, 23));
        $this->third = Phel::set(range(12, 27));
    }
}
