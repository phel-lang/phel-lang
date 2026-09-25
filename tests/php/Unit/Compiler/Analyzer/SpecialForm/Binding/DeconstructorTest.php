<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding;

use Phel;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidatorInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class DeconstructorTest extends TestCase
{
    private Deconstructor $deconstructor;

    protected function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new Deconstructor(
            $this->createStub(BindingValidatorInterface::class),
        );
    }

    public function test_empty_vector(): void
    {
        $bindings = $this->deconstructor->deconstruct(
            Phel::vector([]),
        );

        self::assertSame([], $bindings);
    }

    public function test_vector_with_empty_vectors(): void
    {
        // Test for binding like this (let [[a] [10]
        //                                  [b] [20]])
        // This will be destructured to this:
        // (let [__phel_1 [10]
        //       __phel_2 (php/instanceof __phel_1 PersistentVectorInterface)
        //       __phel_3 (if (php/=== __phel_2 true) (php/aget __phel_1 0) (first __phel_1))
        //       __phel_4 (if (php/=== __phel_2 true) nil (next __phel_1))
        //       a __phel_3
        //       __phel_5 [20]
        //       __phel_6 (php/instanceof __phel_5 PersistentVectorInterface)
        //       __phel_7 (if (php/=== __phel_6 true) (php/aget __phel_5 0) (first __phel_5))
        //       __phel_8 (if (php/=== __phel_6 true) nil (next __phel_5))
        //       b __phel_7])
        $list = Phel::vector([
            Phel::vector([Symbol::create('a')]),
            Phel::vector([10]),
            Phel::vector([Symbol::create('b')]),
            Phel::vector([20]),
        ]);

        $bindings = $this->deconstructor->deconstruct($list);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Phel::vector([10]),
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
                Symbol::create('a'),
                SequentialBindingForms::synthetic('__phel_3'),
            ],
            [
                Symbol::create('__phel_5'),
                Phel::vector([20]),
            ],
            [
                SequentialBindingForms::synthetic('__phel_6'),
                SequentialBindingForms::isVector('__phel_5'),
            ],
            [
                SequentialBindingForms::synthetic('__phel_7'),
                SequentialBindingForms::positional('__phel_6', '__phel_5', '__phel_5', 0),
            ],
            [
                SequentialBindingForms::synthetic('__phel_8'),
                SequentialBindingForms::step('__phel_6', '__phel_5', '__phel_5'),
            ],
            [
                Symbol::create('b'),
                SequentialBindingForms::synthetic('__phel_7'),
            ],
        ], $bindings);
    }

    public function test_table_binding(): void
    {
        // Test for binding like this (let [{:key a} x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel 2 (get __phel_1 :key)
        //       a __phel_2])
        $bindings = $this->deconstructor->deconstruct(
            Phel::vector([
                Phel::map(Keyword::create('key'), Symbol::create('a')),
                Symbol::create('x'),
            ]),
        );

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create('x'),
            ],
            [
                Symbol::create('__phel_2'),
                Phel::list([
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    Keyword::create('key'),
                ]),
            ],
            [
                Symbol::create('a'),
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function test_nil_binding(): void
    {
        // Test for binding like this (let [nil x])
        // This will be destructured to this:
        // (let [])
        $bindings = $this->deconstructor->deconstruct(Phel::vector([null, Symbol::create('x')]));

        self::assertSame([], $bindings);
    }
}
