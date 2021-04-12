<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Reader;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Reader\QuasiquoteTransformer;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuasiquoteTest extends TestCase
{
    public function testTransformUnquote(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            1,
            $q->transform(TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_UNQUOTE), 1]))
        );
    }

    public function testTransformUnquoteSplicing(): void
    {
        $this->expectException(RuntimeException::class);
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $q->transform(TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_UNQUOTE_SPLICING), 1]));
    }

    public function testTransformCreateList(): void
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

    public function testTransformCreateListWithUnquoteSplicing(): void
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

    public function testTransformCreateListWithUnquote(): void
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

    public function testTransformCreateVector(): void
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

    public function testTransformCreateMap(): void
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
            $q->transform(TypeFactory::getInstance()->persistentHashMapFromKVs('a', 1, 'b', 2))
        );
    }

    public function testTransformCreateTable(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_TABLE),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 'a']),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 'b']),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(Table::fromKVs('a', 1, 'b', 2))
        );
    }

    public function testTransformCreatePhelArray(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            TypeFactory::getInstance()->persistentListFromArray([
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_ARRAY),
                TypeFactory::getInstance()->persistentListFromArray([
                    Symbol::create(Symbol::NAME_CONCAT),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 1]),
                    TypeFactory::getInstance()->persistentListFromArray([Symbol::create(Symbol::NAME_LIST), 2]),
                ]),
            ]),
            $q->transform(PhelArray::create(1, 2))
        );
    }

    public function testTransformInt(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            1,
            $q->transform(1)
        );
    }

    public function testTransformString(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            'a',
            $q->transform('a')
        );
    }

    public function testTransformFloat(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            1.1,
            $q->transform(1.1)
        );
    }

    public function testTransformBoolean(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            true,
            $q->transform(true)
        );
    }

    public function testTransformNull(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            null,
            $q->transform(null)
        );
    }

    public function testTransformKeyword(): void
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        self::assertEquals(
            new Keyword('test'),
            $q->transform(new Keyword('test'))
        );
    }

    public function testTransformUnknownSymbol(): void
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

    public function testTransformGlobalSymbol(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('test', Symbol::create('abc'), TypeFactory::getInstance()->emptyPersistentHashMap());

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
