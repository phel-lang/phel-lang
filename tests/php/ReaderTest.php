<?php

namespace Phel\Reader;

use Phel\Exceptions\ReaderException;
use Phel\Lang\Keyword;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Reader;
use Phel\Stream\SourceLocation;
use Phel\Stream\StringCharStream;
use \PHPUnit\Framework\TestCase;

ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');

class ReaderTest extends TestCase {

    public function testReadNumber() {
        $this->assertEquals(1, $this->read('1'));
        $this->assertEquals(10, $this->read('10'));
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

    public function testReadKeyword() {
        $this->assertEquals(
            $this->loc(new Keyword('test'), 1, 1, 1, 5),
            $this->read(':test')
        );
    }

    public function testReadBoolean() {
        $this->assertEquals(true, $this->read('true'));
        $this->assertEquals(false, $this->read('false'));
    }

    public function testReadNil() {
        $this->assertNull(
            $this->read('nil')
        );
    }

    public function testReadSymbol() {
        $this->assertEquals(
            $this->loc(new Symbol('test'), 1, 1, 1, 4), 
            $this->read('test')
        );
    }

    public function testRdlist() {
        $this->assertEquals(
            $this->loc(new Tuple([], false), 1, 1, 1, 2),
            $this->read('()')
        );
        $this->assertEquals(
            $this->loc(new Tuple([$this->loc(new Tuple([], false), 1, 2, 1, 3)], false), 1, 1, 1, 4),
            $this->read('(())')
        );

        $this->assertEquals(
            $this->loc(new Tuple([$this->loc(new Symbol('a'), 1, 2, 1, 2)], false), 1, 1, 1, 3),
            $this->read('(a)')
        );

        $this->assertEquals(
            $this->loc(new Tuple([$this->loc(new Symbol('a'), 1, 2, 1, 2), $this->loc(new Symbol('b'), 1, 4, 1, 4)], false), 1, 1, 1, 5),
            $this->read('(a b)')
        );
    }

    public function testRdlistBracket() {
        $this->assertEquals(
            $this->loc(new Tuple([], true), 1, 1, 1, 2),
            $this->read('[]')
        );
        $this->assertEquals(
            $this->loc(new Tuple([$this->loc(new Tuple([], true), 1, 2, 1, 3)], true), 1, 1, 1, 4),
            $this->read('[[]]')
        );

        $this->assertEquals(
            $this->loc(new Tuple([$this->loc(new Symbol('a'), 1, 2, 1, 2)], true), 1, 1, 1, 3),
            $this->read('[a]')
        );

        $this->assertEquals(
            $this->loc(new Tuple([$this->loc(new Symbol('a'), 1, 2, 1, 2), $this->loc(new Symbol('b'), 1, 4, 1, 4)], true), 1, 1, 1, 5),
            $this->read('[a b]')
        );
    }

    public function testQuote() {
        $this->assertEquals(
            $this->loc(new Tuple([new Symbol('quote'), $this->loc(new Symbol('a'), 1, 2, 1, 2)]), 1, 1, 1, 2),
            $this->read('\'a')
        );
    }

    public function testUnquote() {
        $this->assertEquals(
            $this->loc(new Tuple([new Symbol('unquote'), $this->loc(new Symbol('a'), 1, 2, 1, 2)]), 1, 1, 1, 2),
            $this->read(',a')
        );
    }

    public function testUnquoteSplice() {
        $this->assertEquals(
            $this->loc(new Tuple([new Symbol('unquote-splicing'), $this->loc(new Symbol('a'), 1, 3, 1, 3)]), 1, 1, 1, 3),
            $this->read(',@a')
        );
    }

    public function testQuasiquote1() {
        $this->assertEquals(
            $this->loc(new Tuple([new Symbol('quote'), $this->loc(new Symbol('unquote'), 1, 2, 1, 8)]), 1, 1, 1, 8),
            $this->read('`unquote')
        );
    }

    public function testQuasiquote2() {
        $this->assertEquals(
            $this->loc(new Tuple([new Symbol('quote'), $this->loc(new Symbol('a'), 1, 2, 1, 2)]), 1, 1, 1, 2),
            $this->read('`a')
        );
    }

    public function testQuasiquote3() {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple (quote foo)) (tuple bar)))', true),
            $this->read('`(foo ,bar)', true)
        );
    }

    public function testQuasiquote4() {
        $this->assertEquals(
            $this->read('\'a', true),
            $this->read('``,a', true)
        );
    }

    public function testQuasiquote5() {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple (quote foo)) bar))', true),
            $this->read('`(foo ,@bar)', true)
        );
    }

    public function testQuasiquote6() {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple foo) bar))', true),
            $this->read('`(,foo ,@bar)', true)
        );
    }

    public function testQuasiquote7() {
        $this->assertEquals(
            $this->read('(apply tuple (concat foo bar))', true),
            $this->read('`(,@foo ,@bar)', true)
        );
    }

    public function testQuasiquote8() {
        $this->assertEquals(
            $this->read('(apply tuple (concat foo bar (tuple 1) (tuple "string") (tuple :keyword) (tuple true) (tuple nil)))', true),
            $this->read('`(,@foo ,@bar 1 "string" :keyword true nil)', true)
        );
    }

    public function testReadString() {
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
            "read \$abc sign",
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

    public function testReadEmptyArray() {
        $this->assertEquals(
            $this->loc(Tuple::create(new Symbol('array')), 1, 1, 1, 3),
            $this->read('@[]')
        );
    }

    public function testReadArray1() {
        $this->assertEquals(
            $this->loc(Tuple::create(new Symbol('array'), 1), 1, 1, 1, 4),
            $this->read('@[1]')
        );
    }

    public function testReadArray2() {
        $this->assertEquals(
            $this->loc(Tuple::create(new Symbol('array'), 1, 2), 1, 1, 1, 6),
            $this->read('@[1 2]')
        );
    }

    public function testReadEmptyTable() {
        $this->assertEquals(
            $this->loc(Tuple::create(new Symbol('table')), 1, 1, 1, 3),
            $this->read('@{}')
        );
    }

    public function testReadTable1() {
        $this->assertEquals(
            $this->loc(Tuple::create(new Symbol('table'), new Keyword('a'), 1), 1, 1, 1, 7),
            $this->read('@{:a 1}')
        );
    }

    public function testReadTable2() {
        $this->assertEquals(
            $this->loc(Tuple::create(new Symbol('table'), new Keyword('a'), 1, new Keyword('b'), 2), 1, 1, 1, 12),
            $this->read('@{:a 1 :b 2}')
        );
    }

    public function testTableUneven() {
        $this->expectException(ReaderException::class);
        $this->read('@{:a}');
    }

    public function read($string, $removeLoc = false) {
        $reader = new Reader();
        $stream = new StringCharStream($string);
        
        $result = $reader->read($stream)->getAst();

        if ($removeLoc) {
            $this->removeLoc($result);
        }

        return $result;
    }

    private function loc(Phel $x, $beginLine, $beginColumn, $endLine, $endColumn) {
        $x->setStartLocation(new SourceLocation('string', $beginLine, $beginColumn));
        $x->setEndLocation(new SourceLocation('string', $endLine, $endColumn));
        return $x;
    }

    private function removeLoc($x) {
        if ($x instanceof Phel) {
            $x->setStartLocation(new SourceLocation('string', 0, 0));
            $x->setEndLocation(new SourceLocation('string', 0, 0));
        }

        if ($x instanceof Tuple || $x instanceof PhelArray) {
            foreach ($x as $elem) {
                $this->removeLoc($elem);
            }
        } else if ($x instanceof Table) {
            foreach ($x as $k => $v) {
                $this->removeLoc($k);
                $this->removeLoc($v);
            }
        }

        return $x;
    }
}