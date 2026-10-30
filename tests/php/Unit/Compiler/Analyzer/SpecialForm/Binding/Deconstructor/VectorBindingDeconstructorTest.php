<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding\Deconstructor;

use Phel;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidatorInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\VectorBindingDeconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\ReturnTypeInferrer;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\AbstractLocatedException;
use PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding\SequentialBindingForms;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;

final class VectorBindingDeconstructorTest extends TestCase
{
    private const string REST_SYMBOL = VectorBindingDeconstructor::REST_SYMBOL_NAME;

    private VectorBindingDeconstructor $deconstructor;

    protected function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new VectorBindingDeconstructor(
            new Deconstructor(
                $this->createStub(BindingValidatorInterface::class),
            ),
        );
    }

    public function test_empty_vector(): void
    {
        // Test for binding like this (let [[] x])
        // This will be destructured to this:
        // (let [__phel_1 x])
        $value = Symbol::create('x');
        $binding = Phel::list([]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::createGenerated('__phel_1'),
                $value,
            ],
        ], $bindings);
    }

    public function test_as_binds_the_original_source(): void
    {
        $all = Symbol::create('all');
        $bindings = [];
        $this->deconstructor->deconstruct(
            $bindings,
            Phel::vector([Symbol::create('a'), Keyword::create('as'), $all]),
            Symbol::create('source'),
        );

        self::assertEquals([$all, $bindings[0][0]], $bindings[count($bindings) - 1]);
    }

    public function test_as_after_rest_binds_the_original_source(): void
    {
        $all = Symbol::create('all');
        $bindings = [];
        $this->deconstructor->deconstruct(
            $bindings,
            Phel::vector([Symbol::create('a'), Symbol::create('&'), Symbol::create('rest'), Keyword::create('as'), $all]),
            Symbol::create('source'),
        );

        self::assertEquals([$all, $bindings[0][0]], $bindings[count($bindings) - 1]);
    }

    public function test_bare_as_requires_a_binding_form(): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage(':as requires a binding form');

        $bindings = [];
        $this->deconstructor->deconstruct(
            $bindings,
            Phel::vector([Symbol::create('a'), Keyword::create('as')]),
            Symbol::create('source'),
        );
    }

    #[DataProvider('trailingAsForms')]
    public function test_as_rejects_trailing_forms(PersistentVectorInterface $pattern): void
    {
        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('No binding forms may follow :as');

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $pattern, Symbol::create('source'));
    }

    public static function trailingAsForms(): iterable
    {
        yield 'position' => [Phel::vector([Symbol::create('a'), Keyword::create('as'), Symbol::create('all'), Symbol::create('extra')])];
        yield 'rest' => [Phel::vector([Symbol::create('&'), Symbol::create('tail'), Keyword::create('as'), Symbol::create('all'), Symbol::create('extra')])];
        yield 'duplicate' => [Phel::vector([Keyword::create('as'), Symbol::create('all'), Keyword::create('as'), Symbol::create('again')])];
        yield 'rest after as' => [Phel::vector([Keyword::create('as'), Symbol::create('all'), Symbol::create('&'), Symbol::create('tail')])];
    }

    public function test_vector_with_one_symbol(): void
    {
        // Test for binding like this (let [[a] x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel_2 (php/instanceof __phel_1 PersistentVectorInterface)
        //       __phel_3 (if (php/=== __phel_2 true) (php/aget __phel_1 0) (first __phel_1))
        //       __phel_4 (if (php/=== __phel_2 true) nil (next __phel_1))
        //       a __phel_3])

        $bindTo = Symbol::create('a');
        $value = Symbol::create('x');
        $binding = Phel::list([$bindTo]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::createGenerated('__phel_1'),
                $value,
            ],
            [
                SequentialBindingForms::synthetic('__phel_2'),
                SequentialBindingForms::isVector('__phel_1'),
            ],
            [
                SequentialBindingForms::synthetic('__phel_3'),
                SequentialBindingForms::positional('__phel_2', '__phel_1', '__phel_1', 0),
            ],
            [
                SequentialBindingForms::synthetic('__phel_4'),
                SequentialBindingForms::step('__phel_2', '__phel_1', '__phel_1'),
            ],
            [
                $bindTo,
                SequentialBindingForms::synthetic('__phel_3'),
            ],
        ], $bindings);
    }

    public function test_vector_with_symbols(): void
    {
        // Test for binding like this (let [[a b] x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel_2 (php/instanceof __phel_1 PersistentVectorInterface)
        //       __phel_3 (if (php/=== __phel_2 true) (php/aget __phel_1 0) (first __phel_1))
        //       __phel_4 (if (php/=== __phel_2 true) nil (next __phel_1))
        //       a __phel_3
        //       __phel_5 (if (php/=== __phel_2 true) (php/aget __phel_1 1) (first __phel_4))
        //       __phel_6 (if (php/=== __phel_2 true) nil (next __phel_4))
        //       b __phel_5])
        $bindings = [];

        $bindToA = Symbol::create('a');
        $bindToB = Symbol::create('b');
        $value = Symbol::create('x');
        $binding = Phel::vector([$bindToA, $bindToB]);

        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::createGenerated('__phel_1'),
                $value,
            ],
            [
                SequentialBindingForms::synthetic('__phel_2'),
                SequentialBindingForms::isVector('__phel_1'),
            ],
            [
                SequentialBindingForms::synthetic('__phel_3'),
                SequentialBindingForms::positional('__phel_2', '__phel_1', '__phel_1', 0),
            ],
            [
                SequentialBindingForms::synthetic('__phel_4'),
                SequentialBindingForms::step('__phel_2', '__phel_1', '__phel_1'),
            ],
            [
                $bindToA,
                SequentialBindingForms::synthetic('__phel_3'),
            ],
            [
                SequentialBindingForms::synthetic('__phel_5'),
                SequentialBindingForms::positional('__phel_2', '__phel_1', '__phel_4', 1),
            ],
            [
                SequentialBindingForms::synthetic('__phel_6'),
                SequentialBindingForms::step('__phel_2', '__phel_1', '__phel_4'),
            ],
            [
                $bindToB,
                SequentialBindingForms::synthetic('__phel_5'),
            ],
        ], $bindings);
    }

    public function test_vector_with_one_symbol_with_rest(): void
    {
        // Test for binding like this (let [[a & b] x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel_2 (php/instanceof __phel_1 PersistentVectorInterface)
        //       __phel_3 (if (php/=== __phel_2 true) (php/aget __phel_1 0) (first __phel_1))
        //       __phel_4 (if (php/=== __phel_2 true) nil (next __phel_1))
        //       a __phel_3
        //       __phel_5 (if (php/=== __phel_2 true) (.cdr __phel_1) __phel_4)
        //       b __phel_5])

        $bindToA = Symbol::create('a');
        $bindToB = Symbol::create('b');
        $value = Symbol::create('x');
        $binding = Phel::vector([
            $bindToA,
            Symbol::create(self::REST_SYMBOL),
            $bindToB,
        ]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [
                Symbol::createGenerated('__phel_1'),
                $value,
            ],
            [
                SequentialBindingForms::synthetic('__phel_2'),
                SequentialBindingForms::isVector('__phel_1'),
            ],
            [
                SequentialBindingForms::synthetic('__phel_3'),
                SequentialBindingForms::positional('__phel_2', '__phel_1', '__phel_1', 0),
            ],
            [
                SequentialBindingForms::synthetic('__phel_4'),
                SequentialBindingForms::step('__phel_2', '__phel_1', '__phel_1'),
            ],
            [
                $bindToA,
                SequentialBindingForms::synthetic('__phel_3'),
            ],
            [
                SequentialBindingForms::synthetic('__phel_5'),
                SequentialBindingForms::rest('__phel_2', '__phel_1', '__phel_4', 1),
            ],
            [
                $bindToB,
                SequentialBindingForms::synthetic('__phel_5'),
            ],
        ], $bindings);
    }

    public function test_bare_rest_binds_the_value_without_a_guard(): void
    {
        // (let [[& r] x]) => (let [__phel_1 x  __phel_2 __phel_1  r __phel_2])
        $bindTo = Symbol::create('r');
        $value = Symbol::create('x');
        $binding = Phel::vector([Symbol::create(self::REST_SYMBOL), $bindTo]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            [Symbol::createGenerated('__phel_1'), $value],
            [Symbol::createGenerated('__phel_2'), Symbol::createGenerated('__phel_1')],
            [$bindTo, Symbol::createGenerated('__phel_2')],
        ], $bindings);
    }

    public function test_rest_after_two_positions_steps_the_vector_twice(): void
    {
        // (let [[a b & r] x]): the tail is `nthNext` 2 of the vector, or the
        // walk left by the second `next`.
        $binding = Phel::vector([
            Symbol::create('a'),
            Symbol::create('b'),
            Symbol::create(self::REST_SYMBOL),
            Symbol::create('r'),
        ]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, Symbol::create('x'));

        self::assertEquals(
            [SequentialBindingForms::synthetic('__phel_7'), SequentialBindingForms::rest('__phel_2', '__phel_1', '__phel_6', 2)],
            $bindings[8],
        );
    }

    public function test_the_vector_test_is_bound_once_per_pattern(): void
    {
        $binding = Phel::vector([Symbol::create('a'), Symbol::create('b'), Symbol::create('c')]);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, Symbol::create('x'));

        $vectorTests = array_filter(
            $bindings,
            static fn(array $b): bool => $b[1] == SequentialBindingForms::isVector('__phel_1'),
        );
        self::assertCount(1, $vectorTests);
    }

    public function test_the_vector_test_is_marked_as_compiler_plumbing(): void
    {
        // Its `php/instanceof` must not count as the user's operator when
        // `ReturnTypeInferrer` decides whether to publish a return type.
        $bindings = [];
        $this->deconstructor->deconstruct($bindings, Phel::vector([Symbol::create('a')]), Symbol::create('x'));

        $meta = $bindings[1][0]->getMeta();
        self::assertNotNull($meta);
        self::assertTrue($meta->find(Keyword::create(ReturnTypeInferrer::SYNTHETIC_BINDING)));
    }

    public function test_exception_when_multiple_rest_symbol(): void
    {
        $bindToA = Symbol::create('a');
        $bindToB = Symbol::create('b');
        $value = Symbol::create('x');
        $binding = Phel::vector([
            $bindToA,
            Symbol::create(self::REST_SYMBOL),
            Symbol::create(self::REST_SYMBOL),
            $bindToB,
        ]);

        $this->expectException(AbstractLocatedException::class);
        $this->expectExceptionMessage('only one symbol can follow the & parameter');

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);
    }
}
