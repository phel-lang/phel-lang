<?php

declare(strict_types=1);

namespace PhelTest\Unit;

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
use Phel\Compiler\Lexer;
use Phel\Compiler\Reader;
use PHPUnit\Framework\TestCase;

ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');

final class ReaderTest extends TestCase
{
    public function testReadNumber(): void
    {
        self::assertEquals(1, $this->read('1'));
        self::assertEquals(10, $this->read('10'));
        self::assertEquals(100, $this->read('100'));
        self::assertEquals(10.0, $this->read('10.0'));
        self::assertEquals(1.1, $this->read('1.1'));
        self::assertEquals(10.11, $this->read('10.11'));
        self::assertEquals(1337, $this->read('0x539'));
        self::assertEquals(1337, $this->read('0x5_3_9'));
        self::assertEquals(1337, $this->read('02471'));
        self::assertEquals(1337, $this->read('024_71'));
        self::assertEquals(1337, $this->read('0b10100111001'));
        self::assertEquals(1337, $this->read('0b0101_0011_1001'));
        self::assertEquals(1337, $this->read('1337e0'));
        self::assertEquals(-1337, $this->read('-1337'));
        self::assertEquals(-1337.0, $this->read('-1337.0'));
        self::assertEquals(1337, $this->read('+1337'));
        self::assertEquals(1337, $this->read('+1337.0'));
        self::assertEquals(1.2e3, $this->read('1.2e3'));
        self::assertEquals(7E-10, $this->read('7E-10'));
    }

    public function testReadKeyword(): void
    {
        self::assertEquals(
            $this->loc(new Keyword('test'), 1, 0, 1, 5),
            $this->read(':test')
        );
    }

    public function testReadBoolean(): void
    {
        self::assertEquals(true, $this->read('true'));
        self::assertEquals(false, $this->read('false'));
    }

    public function testReadNil(): void
    {
        $this->assertNull(
            $this->read('nil')
        );
    }

    public function testReadSymbol(): void
    {
        self::assertEquals(
            $this->loc(Symbol::create('test'), 1, 0, 1, 4),
            $this->read('test')
        );
    }

    public function testReadList(): void
    {
        self::assertEquals(
            $this->loc(new Tuple([], false), 1, 0, 1, 2),
            $this->read('()')
        );
        self::assertEquals(
            $this->loc(new Tuple([
                $this->loc(new Tuple([], false), 1, 1, 1, 3),
            ], false), 1, 0, 1, 4),
            $this->read('(())')
        );

        self::assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ], false), 1, 0, 1, 3),
            $this->read('(a)')
        );

        self::assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('b'), 1, 3, 1, 4),
            ], false), 1, 0, 1, 5),
            $this->read('(a b)')
        );
    }

    public function testRdlistBracket(): void
    {
        self::assertEquals(
            $this->loc(new Tuple([], true), 1, 0, 1, 2),
            $this->read('[]')
        );
        self::assertEquals(
            $this->loc(new Tuple([
                $this->loc(new Tuple([], true), 1, 1, 1, 3),
            ], true), 1, 0, 1, 4),
            $this->read('[[]]')
        );

        self::assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ], true), 1, 0, 1, 3),
            $this->read('[a]')
        );

        self::assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
                $this->loc(Symbol::create('b'), 1, 3, 1, 4),
            ], true), 1, 0, 1, 5),
            $this->read('[a b]')
        );
    }

    public function testQuote(): void
    {
        self::assertEquals(
            $this->loc(new Tuple([
                Symbol::create(Symbol::NAME_QUOTE),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read('\'a')
        );
    }

    public function testUnquote(): void
    {
        self::assertEquals(
            $this->loc(new Tuple([
                Symbol::create(Symbol::NAME_UNQUOTE),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read(',a')
        );
    }

    public function testUnquoteSplice(): void
    {
        self::assertEquals(
            $this->loc(new Tuple([
                Symbol::create(Symbol::NAME_UNQUOTE_SPLICING),
                $this->loc(Symbol::create('a'), 1, 2, 1, 3),
            ]), 1, 0, 1, 3),
            $this->read(',@a')
        );
    }

    public function testQuasiquote1(): void
    {
        self::assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create(Symbol::NAME_QUOTE), 1, 1, 1, 8),
                $this->loc(Symbol::create(Symbol::NAME_UNQUOTE), 1, 1, 1, 8),
            ]), 1, 0, 1, 8),
            $this->read(sprintf('`%s', Symbol::NAME_UNQUOTE))
        );
    }

    public function testQuasiquote2(): void
    {
        self::assertEquals(
            $this->loc(new Tuple([
                $this->loc(Symbol::create(Symbol::NAME_QUOTE), 1, 1, 1, 2),
                $this->loc(Symbol::create('a'), 1, 1, 1, 2),
            ]), 1, 0, 1, 2),
            $this->read('`a')
        );
    }

    public function testQuasiquote3(): void
    {
        self::assertEquals(
            $this->read('(apply tuple (concat (tuple (quote foo)) (tuple bar)))', true),
            $this->read('`(foo ,bar)', true)
        );
    }

    public function testQuasiquote4(): void
    {
        self::assertEquals(
            $this->read('\'a', true),
            $this->read('``,a', true)
        );
    }

    public function testQuasiquote5(): void
    {
        self::assertEquals(
            $this->read('(apply tuple (concat (tuple (quote foo)) bar))', true),
            $this->read('`(foo ,@bar)', true)
        );
    }

    public function testQuasiquote6(): void
    {
        self::assertEquals(
            $this->read('(apply tuple (concat (tuple foo) bar))', true),
            $this->read('`(,foo ,@bar)', true)
        );
    }

    public function testQuasiquote7(): void
    {
        self::assertEquals(
            $this->read('(apply tuple (concat foo bar))', true),
            $this->read('`(,@foo ,@bar)', true)
        );
    }

    public function testQuasiquote8(): void
    {
        self::assertEquals(
            $this->read('(apply tuple (concat foo bar (tuple 1) (tuple "string") (tuple :keyword) (tuple true) (tuple nil)))', true),
            $this->read('`(,@foo ,@bar 1 "string" :keyword true nil)', true)
        );
    }

    public function testReadString(): void
    {
        self::assertEquals(
            'abc',
            $this->read('"abc"')
        );

        self::assertEquals(
            'ab"c',
            $this->read('"ab\"c"')
        );

        self::assertEquals(
            "\\\r\n\t\f\v\e\$",
            $this->read('"\\\\\r\n\t\f\v\e\$"')
        );

        self::assertEquals(
            'read $abc sign',
            $this->read('"read $abc sign"')
        );

        self::assertEquals(
            "\x41",
            $this->read('"\x41"')
        );

        self::assertEquals(
            "\u{1000}",
            $this->read('"\u{1000}"')
        );
    }

    public function testReadEmptyArray(): void
    {
        self::assertEquals(
            $this->loc(PhelArray::create(), 1, 0, 1, 3),
            $this->read('@[]')
        );
    }

    public function testReadArray1(): void
    {
        self::assertEquals(
            $this->loc(PhelArray::create(1), 1, 0, 1, 4),
            $this->read('@[1]')
        );
    }

    public function testReadArray2(): void
    {
        self::assertEquals(
            $this->loc(PhelArray::create(1, 2), 1, 0, 1, 6),
            $this->read('@[1 2]')
        );
    }

    public function testReadEmptyTable(): void
    {
        self::assertEquals(
            $this->loc(Table::fromKVs(), 1, 0, 1, 3),
            $this->read('@{}')
        );
    }

    public function testReadTable1(): void
    {
        self::assertEquals(
            $this->loc(Table::fromKVs($this->loc(new Keyword('a'), 1, 2, 1, 4), 1), 1, 0, 1, 7),
            $this->read('@{:a 1}')
        );
    }

    public function testReadTable2(): void
    {
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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
        self::assertEquals(
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

    /** @return AbstractType|string|float|int|bool|null */
    public function read($string, bool $removeLoc = false)
    {
        Symbol::resetGen();
        $reader = new Reader(new GlobalEnvironment());
        $tokenStream = (new Lexer())->lexString($string);

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

    /** @param AbstractType|string|float|int|bool|null $x */
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
