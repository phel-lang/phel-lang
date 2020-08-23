<?php

declare(strict_types=1);

namespace PhelTest;

use Phel\Exceptions\ReaderException;
use Phel\GlobalEnvironment;
use Phel\Lang\AbstractType;
use Phel\Lang\IMeta;
use Phel\Lang\Keyword;
use Phel\Lang\PhelArray;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Lexer;
use Phel\Reader;
use PHPUnit\Framework\TestCase;

ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');

final class ReaderTest extends TestCase
{
    public function testReadNumber(): void
    {
        $this->assertEquals(1, $this->read('1'));
        $this->assertEquals(10, $this->read('10'));
        $this->assertEquals(100, $this->read('100'));
        $this->assertEquals(10.0, $this->read('10.0'));
        $this->assertEquals(1.1, $this->read('1.1'));
        $this->assertEquals(10.11, $this->read('10.11'));
        $this->assertEquals(1337, $this->read('0x539'));
        $this->assertEquals(1337, $this->read('0x5_3_9'));
        $this->assertEquals(1337, $this->read('02471'));
        $this->assertEquals(1337, $this->read('024_71'));
        $this->assertEquals(1337, $this->read('0b10100111001'));
        $this->assertEquals(1337, $this->read('0b0101_0011_1001'));
        $this->assertEquals(1337, $this->read('1337e0'));
        $this->assertEquals(-1337, $this->read('-1337'));
        $this->assertEquals(-1337.0, $this->read('-1337.0'));
        $this->assertEquals(1337, $this->read('+1337'));
        $this->assertEquals(1337, $this->read('+1337.0'));
        $this->assertEquals(1.2e3, $this->read('1.2e3'));
        $this->assertEquals(7E-10, $this->read('7E-10'));
    }

    public function testReadKeyword(): void
    {
        $this->assertEquals(
            $this->loc(new Keyword('test'), 1, 0, 1, 5),
            $this->read(':test')
        );
    }

    public function testReadBoolean(): void
    {
        $this->assertEquals(true, $this->read('true'));
        $this->assertEquals(false, $this->read('false'));
    }

    public function testReadNil(): void
    {
        $this->assertNull(
            $this->read('nil')
        );
    }

    public function testReadSymbol(): void
    {
        $this->assertEquals(
            $this->loc(Symbol::create('test'), 1, 0, 1, 4),
            $this->read('test')
        );
    }

    public function testReadList(): void
    {
        $this->assertEquals(
            $this->loc(new Tuple([], false), 1, 0, 1, 2),
            $this->read('()')
        );
        $this->assertEquals(
            $this->loc(new Tuple([
                $this->loc(new Tuple([], false), 1, 1, 1, 3),
            ], false), 1, 0, 1, 4),
            $this->read('(())')
        );

        $this->assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ], false), 1, 0, 1, 3),
            $this->read('(a)')
        );

        $this->assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('b'), 1, 3, 1, 4),
            ], false), 1, 0, 1, 5),
            $this->read('(a b)')
        );
    }

    public function testRdlistBracket(): void
    {
        $this->assertEquals(
            $this->loc(new Tuple([], true), 1, 0, 1, 2),
            $this->read('[]')
        );
        $this->assertEquals(
            $this->loc(new Tuple([
                $this->loc(new Tuple([], true), 1, 1, 1, 3),
            ], true), 1, 0, 1, 4),
            $this->read('[[]]')
        );

        $this->assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ], true), 1, 0, 1, 3),
            $this->read('[a]')
        );

        $this->assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('b'), 1, 3, 1, 4),
            ], true), 1, 0, 1, 5),
            $this->read('[a b]')
        );
    }

    public function testQuote(): void
    {
        $this->assertEquals(
            $this->loc(new Tuple([
                Symbol::create(Symbol::NAME_QUOTE),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read('\'a')
        );
    }

    public function testUnquote(): void
    {
        $this->assertEquals(
            $this->loc(new Tuple([
                Symbol::create(Symbol::NAME_UNQUOTE),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read(',a')
        );
    }

    public function testUnquoteSplice(): void
    {
        $this->assertEquals(
            $this->loc(new Tuple([
                Symbol::create(Symbol::NAME_UNQUOTE_SPLICING),
                $this->loc(Symbol::create('a'), 1, 2, 1, 3),
            ]), 1, 0, 1, 3),
            $this->read(',@a')
        );
    }

    public function testQuasiquote1(): void
    {
        $this->assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create(Symbol::NAME_QUOTE), 1, 1, 1, 8),
                $this->loc(Symbol::create(Symbol::NAME_UNQUOTE), 1, 1, 1, 8),
            ]), 1, 0, 1, 8),
            $this->read(sprintf('`%s', Symbol::NAME_UNQUOTE))
        );
    }

    public function testQuasiquote2(): void
    {
        $this->assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create(Symbol::NAME_QUOTE), 1, 1, 1, 2),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read('`a')
        );
    }

    public function testQuasiquote3(): void
    {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple (quote foo)) (tuple bar)))', true),
            $this->read('`(foo ,bar)', true)
        );
    }

    public function testQuasiquote4(): void
    {
        $this->assertEquals(
            $this->read('\'a', true),
            $this->read('``,a', true)
        );
    }

    public function testQuasiquote5(): void
    {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple (quote foo)) bar))', true),
            $this->read('`(foo ,@bar)', true)
        );
    }

    public function testQuasiquote6(): void
    {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple foo) bar))', true),
            $this->read('`(,foo ,@bar)', true)
        );
    }

    public function testQuasiquote7(): void
    {
        $this->assertEquals(
            $this->read('(apply tuple (concat foo bar))', true),
            $this->read('`(,@foo ,@bar)', true)
        );
    }

    public function testQuasiquote8(): void
    {
        $this->assertEquals(
            $this->read('(apply tuple (concat foo bar (tuple 1) (tuple "string") (tuple :keyword) (tuple true) (tuple nil)))', true),
            $this->read('`(,@foo ,@bar 1 "string" :keyword true nil)', true)
        );
    }

    public function testReadString(): void
    {
        $this->assertEquals(
            'abc',
            $this->read('"abc"')
        );

        $this->assertEquals(
            'ab"c',
            $this->read('"ab\"c"')
        );

        $this->assertEquals(
            "\\\r\n\t\f\v\e\$",
            $this->read('"\\\\\r\n\t\f\v\e\$"')
        );

        $this->assertEquals(
            'read $abc sign',
            $this->read('"read $abc sign"')
        );

        $this->assertEquals(
            "\x41",
            $this->read('"\x41"')
        );

        $this->assertEquals(
            "\u{1000}",
            $this->read('"\u{1000}"')
        );
    }

    public function testReadEmptyArray(): void
    {
        $this->assertEquals(
            $this->loc(PhelArray::create(), 1, 0, 1, 3),
            $this->read('@[]')
        );
    }

    public function testReadArray1(): void
    {
        $this->assertEquals(
            $this->loc(PhelArray::create(1), 1, 0, 1, 4),
            $this->read('@[1]')
        );
    }

    public function testReadArray2(): void
    {
        $this->assertEquals(
            $this->loc(PhelArray::create(1, 2), 1, 0, 1, 6),
            $this->read('@[1 2]')
        );
    }

    public function testReadEmptyTable(): void
    {
        $this->assertEquals(
            $this->loc(Table::fromKVs(), 1, 0, 1, 3),
            $this->read('@{}')
        );
    }

    public function testReadTable1(): void
    {
        $this->assertEquals(
            $this->loc(Table::fromKVs($this->loc(new Keyword('a'), 1, 2, 1, 4), 1), 1, 0, 1, 7),
            $this->read('@{:a 1}')
        );
    }

    public function testReadTable2(): void
    {
        $this->assertEquals(
            $this->loc(Table::fromKVs(
                $this->loc(new Keyword('a'), 1, 2, 1, 4),
                1,
                $this->loc(new Keyword('b'), 1, 7, 1, 9),
                2
            ), 1, 0, 1, 12),
            $this->read('@{:a 1 :b 2}')
        );
    }

    public function testTableUneven(): void
    {
        $this->expectException(ReaderException::class);
        $this->read('@{:a}');
    }

    public function testMetaKeyword(): void
    {
        $this->assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Table::fromKVs(
                        $this->loc(new Keyword('test'), 1, 1, 1, 6),
                        true
                    )
                ),
                1,
                7,
                1,
                11
            ),
            $this->read('^:test test')
        );
    }

    public function testMetaString(): void
    {
        $this->assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Table::fromKVs(new Keyword('tag'), 'test')
                ),
                1,
                8,
                1,
                12
            ),
            $this->read('^"test" test')
        );
    }

    public function testMetaSymbol(): void
    {
        $this->assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Table::fromKVs(
                        new Keyword('tag'),
                        $this->loc(Symbol::create('String'), 1, 1, 1, 7)
                    )
                ),
                1,
                8,
                1,
                12
            ),
            $this->read('^String test')
        );
    }

    public function testMetaTable(): void
    {
        $this->assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Table::fromKVs(
                        $this->loc(new Keyword('a'), 1, 3, 1, 5),
                        1,
                        $this->loc(new Keyword('b'), 1, 8, 1, 10),
                        2
                    )
                ),
                1,
                14,
                1,
                18
            ),
            $this->read('^@{:a 1 :b 2} test')
        );
    }

    public function testConcatMeta(): void
    {
        $this->assertEquals(
            $this->loc(
                $this->withMeta(
                    Symbol::create('test'),
                    Table::fromKVs(
                        $this->loc(new Keyword('a'), 1, 1, 1, 3),
                        true,
                        $this->loc(new Keyword('b'), 1, 5, 1, 7),
                        true
                    )
                ),
                1,
                8,
                1,
                12
            ),
            $this->read('^:a ^:b test')
        );
    }

    public function testReadShortFnZeroArgs(): void
    {
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_FN),
                Tuple::createBracket(),
                $this->loc(
                    Tuple::create(
                        $this->loc(Symbol::create('add'), 1, 2, 1, 5)
                    ),
                    1,
                    0,
                    1,
                    6
                )
            ),
            $this->read('|(add)')
        );
    }

    public function testReadShortFnOneArg(): void
    {
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_FN),
                Tuple::createBracket(
                    Symbol::create('__short_fn_1_1')
                ),
                $this->loc(
                    Tuple::create(
                        $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                        $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7)
                    ),
                    1,
                    0,
                    1,
                    8
                )
            ),
            $this->read('|(add $)')
        );
    }

    public function testReadShortFnOneArgTwoTimes(): void
    {
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_FN),
                Tuple::createBracket(
                    Symbol::create('__short_fn_1_1')
                ),
                $this->loc(
                    Tuple::create(
                        $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                        $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 7),
                        $this->loc(Symbol::create('__short_fn_1_1'), 1, 8, 1, 9)
                    ),
                    1,
                    0,
                    1,
                    10
                )
            ),
            $this->read('|(add $ $)')
        );
    }

    public function testReadShortFnTwoArguments(): void
    {
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_FN),
                Tuple::createBracket(
                    Symbol::create('__short_fn_1_1'),
                    Symbol::create('__short_fn_2_2')
                ),
                $this->loc(
                    Tuple::create(
                        $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                        $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                        $this->loc(Symbol::create('__short_fn_2_2'), 1, 9, 1, 11)
                    ),
                    1,
                    0,
                    1,
                    12
                )
            ),
            $this->read('|(add $1 $2)')
        );
    }

    public function testReadShortFnMissingArgument(): void
    {
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_FN),
                Tuple::createBracket(
                    Symbol::create('__short_fn_1_1'),
                    Symbol::create('__short_fn_undefined_3'),
                    Symbol::create('__short_fn_3_2')
                ),
                $this->loc(
                    Tuple::create(
                        $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                        $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                        $this->loc(Symbol::create('__short_fn_3_2'), 1, 9, 1, 11)
                    ),
                    1,
                    0,
                    1,
                    12
                )
            ),
            $this->read('|(add $1 $3)')
        );
    }

    public function testReadShortFnRestArguments(): void
    {
        $this->assertEquals(
            Tuple::create(
                Symbol::create(Symbol::NAME_FN),
                Tuple::createBracket(
                    Symbol::create('__short_fn_1_1'),
                    Symbol::create('&'),
                    Symbol::create('__short_fn_rest_2')
                ),
                $this->loc(
                    Tuple::create(
                        $this->loc(Symbol::create('add'), 1, 2, 1, 5),
                        $this->loc(Symbol::create('__short_fn_1_1'), 1, 6, 1, 8),
                        $this->loc(Symbol::create('__short_fn_rest_2'), 1, 9, 1, 11)
                    ),
                    1,
                    0,
                    1,
                    12
                )
            ),
            $this->read('|(add $1 $&)')
        );
    }

    /** @return AbstractType|scalar|null */
    public function read($string, bool $removeLoc = false)
    {
        Symbol::resetGen();
        $globalEnv = new GlobalEnvironment();
        $lexer = new Lexer();
        $reader = new Reader($globalEnv);
        $tokenStream = $lexer->lexString($string);

        $result = $reader->readNext($tokenStream)->getAst();

        if ($removeLoc) {
            $this->removeLoc($result);
        }

        return $result;
    }

    private function withMeta(IMeta $x, Table $t): IMeta
    {
        $x->setMeta($t);
        return $x;
    }

    private function loc(AbstractType $x, $beginLine, $beginColumn, $endLine, $endColumn): AbstractType
    {
        $x->setStartLocation(new SourceLocation('string', $beginLine, $beginColumn));
        $x->setEndLocation(new SourceLocation('string', $endLine, $endColumn));
        return $x;
    }

    /** @param AbstractType|scalar|null $x */
    private function removeLoc($x)
    {
        if ($x instanceof AbstractType) {
            $x->setStartLocation(new SourceLocation('string', 0, 0));
            $x->setEndLocation(new SourceLocation('string', 0, 0));
        }

        if ($x instanceof Tuple || $x instanceof PhelArray) {
            foreach ($x as $elem) {
                $this->removeLoc($elem);
            }
        } elseif ($x instanceof Table) {
            foreach ($x as $k => $v) {
                $this->removeLoc($k);
                $this->removeLoc($v);
            }
        }

        return $x;
    }
}
