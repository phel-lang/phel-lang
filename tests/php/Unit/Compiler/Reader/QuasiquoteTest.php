<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Reader;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Reader\QuasiquoteTransformer;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuasiquoteTest extends TestCase
{
    public function test_transform_unquote(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            1,
            $q->transform(TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_UNQUOTE), 1]))
        );
    }

    public function test_transform_unquote_splicing(): void
    {
        $this->expectException(RuntimeException::class);
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $q->transform(TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_UNQUOTE_SPLICING), 1]));
    }

    public function test_transform_create_list(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_LIST),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(TypeFactory::getInstance()->persistentListFromArray([1, 2]))
        );
    }

    public function test_transform_create_list_with_unquote_splicing(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_LIST),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    2,
                ]),
            ]),
            $q->transform(TypeFactory::getInstance()->persistentListFromArray([
                1,
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_UNQUOTE_SPLICING),
                    2,
                ]),
            ]))
        );
    }

    public function test_transform_create_list_with_unquote(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_LIST),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(TypeFactory::getInstance()->persistentListFromArray([
                1,
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_UNQUOTE),
                    2,
                ]),
            ]))
        );
    }

    public function test_transform_create_vector(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_VECTOR),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(TypeFactory::getInstance()->persistentVectorFromArray([1, 2]))
        );
    }

    public function test_transform_create_map(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_MAP),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 'a']),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 'b']),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(TypeFactory::getInstance()->persistentMapFromKVs('a', 1, 'b', 2))
        );
    }

    public function test_transform_int(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            1,
            $q->transform(1)
        );
    }

    public function test_transform_string(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            'a',
            $q->transform('a')
        );
    }

    public function test_transform_float(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            1.1,
            $q->transform(1.1)
        );
    }

    public function test_transform_boolean(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            true,
            $q->transform(true)
        );
    }

    public function test_transform_null(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            null,
            $q->transform(null)
        );
    }

    public function test_transform_keyword(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            Keyword::create('test'),
            $q->transform(Keyword::create('test'))
        );
    }

    public function test_transform_unknown_symbol(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_QUOTE),
                Symbol::create('test'),
            ]),
            $q->transform(Symbol::create('test'))
        );
    }

    public function test_transform_global_symbol(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('test', Symbol::create('abc'), TypeFactory::getInstance()->emptyPersistentMap());

        $q = new QuasiquoteTransformer($env);
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_QUOTE),
                Symbol::createForNamespace('test', 'abc'),
            ]),
            $q->transform(Symbol::createForNamespace('test', 'abc'))
        );
    }
}
