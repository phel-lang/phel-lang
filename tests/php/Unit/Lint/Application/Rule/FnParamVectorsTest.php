<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lint\Application\Rule\FnParamVectors;

use function iterator_to_array;

final class FnParamVectorsTest extends RuleTestCase
{
    public function test_yields_single_param_vector_for_defn(): void
    {
        $form = $this->firstForm('(defn my-fn [x y] (+ x y))');

        $vectors = iterator_to_array(FnParamVectors::of($form), false);

        self::assertCount(1, $vectors);
        self::assertInstanceOf(PersistentVectorInterface::class, $vectors[0]);
        self::assertCount(2, $vectors[0]);
    }

    public function test_yields_multiple_param_vectors_for_multi_arity_defn(): void
    {
        $form = $this->firstForm(<<<'PHEL'
            (defn add
              ([] 0)
              ([x] x)
              ([x y] (+ x y)))
            PHEL);

        $vectors = iterator_to_array(FnParamVectors::of($form), false);

        self::assertCount(3, $vectors);
        self::assertCount(0, $vectors[0]);
        self::assertCount(1, $vectors[1]);
        self::assertCount(2, $vectors[2]);
    }

    public function test_yields_param_vector_for_plain_fn(): void
    {
        $form = $this->firstForm('(fn [a b c] (+ a b c))');

        $vectors = iterator_to_array(FnParamVectors::of($form), false);

        self::assertCount(1, $vectors);
        self::assertCount(3, $vectors[0]);
    }

    public function test_skips_docstring_when_present(): void
    {
        $form = $this->firstForm('(defn f "doc" [x] x)');

        $vectors = iterator_to_array(FnParamVectors::of($form), false);

        self::assertCount(1, $vectors);
        self::assertCount(1, $vectors[0]);
    }

    public function test_yields_nothing_when_no_param_vector_found(): void
    {
        $form = $this->firstForm('(defn f)');

        $vectors = iterator_to_array(FnParamVectors::of($form), false);

        self::assertSame([], $vectors);
    }

    private function firstForm(string $source): PersistentListInterface
    {
        $analysis = $this->buildAnalysis($source);

        /** @var PersistentListInterface $first */
        $first = $analysis->forms[0];
        self::assertInstanceOf(PersistentListInterface::class, $first);

        return $first;
    }
}
