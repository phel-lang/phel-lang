<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding;

use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidatorInterface;
use Phel\Compiler\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class DeconstructorTest extends TestCase
{
    private const EXAMPLE_KEY_1 = 'example key 1';
    private const EXAMPLE_KEY_2 = 'example key 2';
    private const EXAMPLE_KEY_3 = 'example key 3';

    private Deconstructor $deconstructor;

    public function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new Deconstructor(
            $this->createStub(BindingValidatorInterface::class)
        );
    }

    public function test_empty_vector(): void
    {
        $bindings = $this->deconstructor->deconstruct(
            TypeFactory::getInstance()->persistentVectorFromArray([])
        );

        self::assertEquals([], $bindings);
    }

    public function test_vector_with_empty_vectors(): void
    {
        // Test for binding like this (let [[a] [10]
        //                                  [b] [20]])
        // This will be destructured to this:
        // (let [__phel_1 [10]
        //       __phel_2 (first __phel_1)
        //       __phel_3 (next __phel_1)
        //       a __phel_2
        //       __phel_4 [20]
        //       __phel_5 (first __phel_4)
        //       __phel_6 (next __phel_4)
        //       b __phel_5])
        $list = TypeFactory::getInstance()->persistentVectorFromArray([
            TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('a')]),
            TypeFactory::getInstance()->persistentVectorFromArray([10]),
            TypeFactory::getInstance()->persistentVectorFromArray([Symbol::create('b')]),
            TypeFactory::getInstance()->persistentVectorFromArray([20]),
        ]);

        $bindings = $this->deconstructor->deconstruct($list);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                TypeFactory::getInstance()->persistentVectorFromArray([10]),
            ],
            [
                Symbol::create('__phel_2'),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create('first'),
                    Symbol::create('__phel_1'),
                ]),
            ],
            [
                Symbol::create('__phel_3'),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create('next'),
                    Symbol::create('__phel_1'),
                ]),
            ],
            [
                Symbol::create('a'),
                Symbol::create('__phel_2'),
            ],
            [
                Symbol::create('__phel_4'),
                TypeFactory::getInstance()->persistentVectorFromArray([20]),
            ],
            [
                Symbol::create('__phel_5'),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create('first'),
                    Symbol::create('__phel_4'),
                ]),
            ],
            [
                Symbol::create('__phel_6'),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create('next'),
                    Symbol::create('__phel_4'),
                ]),
            ],
            [
                Symbol::create('b'),
                Symbol::create('__phel_5'),
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
            TypeFactory::getInstance()->persistentVectorFromArray([
                TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('key'), Symbol::create('a')),
                Symbol::create('x'),
            ])
        );

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                Symbol::create('x'),
            ],
            [
                Symbol::create('__phel_2'),
                TypeFactory::getInstance()->persistentListFromArray([
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
        $bindings = $this->deconstructor->deconstruct(TypeFactory::getInstance()->persistentVectorFromArray([null, Symbol::create('x')]));

        self::assertEquals([], $bindings);
    }
}
