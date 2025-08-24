<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidatorInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PhelType;
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
            PhelType::persistentVectorFromArray([]),
        );

        self::assertSame([], $bindings);
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
        $list = PhelType::persistentVectorFromArray([
            PhelType::persistentVectorFromArray([Symbol::create('a')]),
            PhelType::persistentVectorFromArray([10]),
            PhelType::persistentVectorFromArray([Symbol::create('b')]),
            PhelType::persistentVectorFromArray([20]),
        ]);

        $bindings = $this->deconstructor->deconstruct($list);

        self::assertEquals([
            [
                Symbol::create('__phel_1'),
                PhelType::persistentVectorFromArray([10]),
            ],
            [
                Symbol::create('__phel_2'),
                PhelType::persistentListFromArray([
                    Symbol::create('first'),
                    Symbol::create('__phel_1'),
                ]),
            ],
            [
                Symbol::create('__phel_3'),
                PhelType::persistentListFromArray([
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
                PhelType::persistentVectorFromArray([20]),
            ],
            [
                Symbol::create('__phel_5'),
                PhelType::persistentListFromArray([
                    Symbol::create('first'),
                    Symbol::create('__phel_4'),
                ]),
            ],
            [
                Symbol::create('__phel_6'),
                PhelType::persistentListFromArray([
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
            PhelType::persistentVectorFromArray([
                PhelType::persistentMapFromKVs(Keyword::create('key'), Symbol::create('a')),
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
                PhelType::persistentListFromArray([
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
        $bindings = $this->deconstructor->deconstruct(PhelType::persistentVectorFromArray([null, Symbol::create('x')]));

        self::assertSame([], $bindings);
    }
}
