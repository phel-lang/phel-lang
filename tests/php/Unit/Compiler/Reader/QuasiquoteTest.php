<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Reader;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Reader\QuasiquoteTransformer;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\Type;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuasiquoteTest extends TestCase
{
    public function test_transform_unquote(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertSame(
            1,
            $q->transform(Type::persistentListFromArray([Symbol::create(Symbol::NAME_UNQUOTE), 1])),
        );
    }

    public function test_transform_unquote_splicing(): void
    {
        $this->expectException(RuntimeException::class);
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $q->transform(Type::persistentListFromArray([Symbol::create(Symbol::NAME_UNQUOTE_SPLICING), 1]));
    }

    public function test_transform_create_list(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            Type::persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_LIST),
                Type::persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(Type::persistentListFromArray([1, 2])),
        );
    }

    public function test_transform_create_list_with_unquote_splicing(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            Type::persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_LIST),
                Type::persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    2,
                ]),
            ]),
            $q->transform(Type::persistentListFromArray([
                1,
                Type::persistentListFromArray([
                    Symbol::create(Symbol::NAME_UNQUOTE_SPLICING),
                    2,
                ]),
            ])),
        );
    }

    public function test_transform_create_list_with_unquote(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            Type::persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_LIST),
                Type::persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(Type::persistentListFromArray([
                1,
                Type::persistentListFromArray([
                    Symbol::create(Symbol::NAME_UNQUOTE),
                    2,
                ]),
            ])),
        );
    }

    public function test_transform_create_vector(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            Type::persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_VECTOR),
                Type::persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(Type::persistentVectorFromArray([1, 2])),
        );
    }

    public function test_transform_create_map(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            Type::persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_MAP),
                Type::persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 'a']),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 'b']),
                    Type::persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(Type::persistentMapFromKVs('a', 1, 'b', 2)),
        );
    }

    public function test_transform_int(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertSame(
            1,
            $q->transform(1),
        );
    }

    public function test_transform_string(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertSame(
            'a',
            $q->transform('a'),
        );
    }

    public function test_transform_float(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertSame(
            1.1,
            $q->transform(1.1),
        );
    }

    public function test_transform_boolean(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertTrue(
            $q->transform(true),
        );
    }

    public function test_transform_null(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertNull(
            $q->transform(null),
        );
    }

    public function test_transform_keyword(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            Keyword::create('test'),
            $q->transform(Keyword::create('test')),
        );
    }

    public function test_transform_unknown_symbol(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            Type::persistentListFromArray([
                Symbol::create(Symbol::NAME_QUOTE),
                Symbol::create('test'),
            ]),
            $q->transform(Symbol::create('test')),
        );
    }

    public function test_transform_global_symbol(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('test', Symbol::create('abc'));

        $q = new QuasiquoteTransformer($env);
        self::assertEquals(
            Type::persistentListFromArray([
                Symbol::create(Symbol::NAME_QUOTE),
                Symbol::createForNamespace('test', 'abc'),
            ]),
            $q->transform(Symbol::createForNamespace('test', 'abc')),
        );
    }
}
