<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm\Binding\Deconstructor;

use Phel;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\BindingValidatorInterface;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\Binding\Deconstructor\MapBindingDeconstructor;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class MapBindingDeconstructorTest extends TestCase
{
    private MapBindingDeconstructor $deconstructor;

    protected function setUp(): void
    {
        Symbol::resetGen();

        $this->deconstructor = new MapBindingDeconstructor(
            new Deconstructor(
                $this->createMock(BindingValidatorInterface::class),
            ),
        );
    }

    public function test_deconstruct_table(): void
    {
        // Test for binding like this (let [{:key a} x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel 2 (get __phel_1 :key)
        //       a __phel_2])

        $key = Keyword::create('key');
        $bindTo = Symbol::create('a');
        $value = Symbol::create('x');
        $binding = Phel::map($key, $bindTo);

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            // __phel_1 x
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            // __phel 2 (get __phel_1 :key)
            [
                Symbol::create('__phel_2'),
                Phel::list([
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    $key,
                ]),
            ],
            // a __phel_2
            [
                $bindTo,
                Symbol::create('__phel_2'),
            ],
        ], $bindings);
    }

    public function test_deconstruct_table_nested_vector(): void
    {
        // Test for binding like this (let [{:key [a]} x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel 2 (get __phel_1 :key)
        //       __phel_3 __phel_2
        //       __phel_3 __phel_2
        //       __phel_4 (first __phel_3)
        //       __phel_5 (next __phel_3)
        //       a __phel_4])

        $key = Keyword::create('key');
        $bindTo = Symbol::create('a');
        $value = Symbol::create('x');
        $binding = Phel::map($key, Phel::vector([$bindTo]));

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            // __phel_1 x
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            // __phel 2 (get __phel_1 :key)
            [
                Symbol::create('__phel_2'),
                Phel::list([
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    $key,
                ]),
            ],
            // __phel_3 __phel_2
            [
                Symbol::create('__phel_3'),
                Symbol::create('__phel_2'),
            ],
            // __phel_4 (first __phel_3)
            [
                Symbol::create('__phel_4'),
                Phel::list([
                    Symbol::create('first'),
                    Symbol::create('__phel_3'),
                ]),
            ],
            // __phel_5 (next __phel_3)
            [
                Symbol::create('__phel_5'),
                Phel::list([
                    Symbol::create('next'),
                    Symbol::create('__phel_3'),
                ]),
            ],
            // a __phel_4
            [
                $bindTo,
                Symbol::create('__phel_4'),
            ],
        ], $bindings);
    }

    public function test_deconstruct_keys(): void
    {
        // Test for binding like this (let [{:keys [a b]} x])
        // This will be destructured to this:
        // (let [__phel_1 x
        //       __phel_2 (get __phel_1 :a)
        //       a __phel_2
        //       __phel_3 (get __phel_1 :b)
        //       b __phel_3])

        $binding = Phel::map(
            Keyword::create('keys'),
            Phel::vector([
                Symbol::create('a'),
                Symbol::create('b'),
            ]),
        );
        $value = Symbol::create('x');

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            // __phel_1 x
            [
                Symbol::create('__phel_1'),
                $value,
            ],
            // __phel_2 (get __phel_1 :a)
            [
                Symbol::create('__phel_2'),
                Phel::list([
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    Keyword::create('a'),
                ]),
            ],
            // a __phel_2
            [
                Symbol::create('a'),
                Symbol::create('__phel_2'),
            ],
            // __phel_3 (get __phel_1 :b)
            [
                Symbol::create('__phel_3'),
                Phel::list([
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('__phel_1'),
                    Keyword::create('b'),
                ]),
            ],
            // b __phel_3
            [
                Symbol::create('b'),
                Symbol::create('__phel_3'),
            ],
        ], $bindings);
    }

    public function test_deconstruct_keys_with_as(): void
    {
        // Test for binding like this (let [{:keys [a] :as m} x])
        // This will be destructured to this:
        // (let [m x
        //       __phel_1 (get m :a)
        //       a __phel_1])

        $binding = Phel::map(
            Keyword::create('keys'),
            Phel::vector([
                Symbol::create('a'),
            ]),
            Keyword::create('as'),
            Symbol::create('m'),
        );
        $value = Symbol::create('x');

        $bindings = [];
        $this->deconstructor->deconstruct($bindings, $binding, $value);

        self::assertEquals([
            // m x
            [
                Symbol::create('m'),
                $value,
            ],
            // __phel_1 (get m :a)
            [
                Symbol::create('__phel_1'),
                Phel::list([
                    Symbol::create(Symbol::NAME_PHP_ARRAY_GET),
                    Symbol::create('m'),
                    Keyword::create('a'),
                ]),
            ],
            // a __phel_1
            [
                Symbol::create('a'),
                Symbol::create('__phel_1'),
            ],
        ], $bindings);
    }
}
