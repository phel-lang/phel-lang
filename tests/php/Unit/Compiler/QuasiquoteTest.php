<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Compiler\GlobalEnvironment;
use Phel\Compiler\QuasiquoteTransformer;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QuasiquoteTest extends TestCase
{
    public function testTransformUnquote()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            1,
            $q->transform(Tuple::create(Symbol::create(Symbol::NAME_UNQUOTE), 1))
        );
    }

    public function testTransformUnquoteSplicing()
    {
        $this->expectException(RuntimeException::class);
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $q->transform(Tuple::create(Symbol::create(Symbol::NAME_UNQUOTE_SPLICING), 1));
    }

    public function testTransformCreateTuple()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_TUPLE),
                Tuple::create(
                    Symbol::create(Symbol::NAME_CONCAT),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 1),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 2)
                )
            ),
            $q->transform(Tuple::create(1, 2))
        );
    }

    public function testTransformCreateTupleWithUnquoteSplicing()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_TUPLE),
                Tuple::create(
                    Symbol::create(Symbol::NAME_CONCAT),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 1),
                    2
                )
            ),
            $q->transform(Tuple::create(1, Tuple::create(Symbol::create(Symbol::NAME_UNQUOTE_SPLICING), 2)))
        );
    }

    public function testTransformCreateTupleWithUnquote()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_TUPLE),
                Tuple::create(
                    Symbol::create(Symbol::NAME_CONCAT),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 1),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 2)
                )
            ),
            $q->transform(Tuple::create(1, Tuple::create(Symbol::create(Symbol::NAME_UNQUOTE), 2)))
        );
    }

    public function testTransformCreateTupleBrackets()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_TUPLE_BRACKETS),
                Tuple::create(
                    Symbol::create(Symbol::NAME_CONCAT),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 1),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 2)
                )
            ),
            $q->transform(Tuple::createBracket(1, 2))
        );
    }

    public function testTransformCreateTable()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_TABLE),
                Tuple::create(
                    Symbol::create(Symbol::NAME_CONCAT),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 'a'),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 1),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 'b'),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 2)
                )
            ),
            $q->transform(Table::fromKVs('a', 1, 'b', 2))
        );
    }

    public function testTransformCreatePhelArray()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_APPLY),
                Symbol::create(Symbol::NAME_ARRAY),
                Tuple::create(
                    Symbol::create(Symbol::NAME_CONCAT),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 1),
                    Tuple::create(Symbol::create(Symbol::NAME_TUPLE), 2)
                )
            ),
            $q->transform(PhelArray::create(1, 2))
        );
    }

    public function testTransformInt()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            1,
            $q->transform(1)
        );
    }

    public function testTransformString()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            'a',
            $q->transform('a')
        );
    }

    public function testTransformFloat()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            1.1,
            $q->transform(1.1)
        );
    }

    public function testTransformBoolean()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            true,
            $q->transform(true)
        );
    }

    public function testTransformNull()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            null,
            $q->transform(null)
        );
    }

    public function testTransformKeyword()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            new Keyword('test'),
            $q->transform(new Keyword('test'))
        );
    }

    public function testTransformUnknownSymbol()
    {
        $q = new QuasiquoteTransformer(new GlobalEnvironment());
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_QUOTE),
                Symbol::create('test')
            ),
            $q->transform(Symbol::create('test'))
        );
    }

    public function testTransformGlobalSymbol()
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('test', Symbol::create('abc'), Table::fromKVs());

        $q = new QuasiquoteTransformer($env);
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_QUOTE),
                Symbol::createForNamespace('test', 'abc')
            ),
            $q->transform(Symbol::createForNamespace('test', 'abc'))
        );
    }
}
