<?php

namespace Phel\Reader;

use Phel\Exceptions\ReaderException;
use Phel\Lang\Boolean;
use Phel\Lang\Keyword;
use Phel\Lang\Nil;
use Phel\Lang\Number;
use Phel\Lang\PhelArray;
use Phel\Lang\PhelString;
use Phel\Lang\Symbol;
use Phel\Lang\Table;
use Phel\Lang\Tuple;
use Phel\Reader;
use Phel\Stream\StringCharStream;
use \PHPUnit\Framework\TestCase;

ini_set('xdebug.var_display_max_depth', '10');
ini_set('xdebug.var_display_max_children', '256');
ini_set('xdebug.var_display_max_data', '1024');

class ReaderTest extends TestCase {

    public function testReadNumber() {
        $this->assertEquals(new Number(1), $this->read('1'));
        $this->assertEquals(new Number(10), $this->read('10'));
        $this->assertEquals(new Number(1.1), $this->read('1.1'));
        $this->assertEquals(new Number(10.11), $this->read('10.11'));
    }

    public function testReadKeyword() {
        $this->assertEquals(new Keyword('test'), $this->read(':test'));
    }

    public function testReadBoolean() {
        $this->assertEquals(new Boolean(true), $this->read('true'));
        $this->assertEquals(new Boolean(false), $this->read('false'));
    }

    public function testReadNil() {
        $this->assertEquals(Nil::getInstance(), $this->read('nil'));
    }

    public function testReadSymbol() {
        $this->assertEquals(new Symbol('test'), $this->read('test'));
    }

    public function testRdlist() {
        $this->assertEquals(
            new Tuple([]),
            $this->read('()')
        );
        $this->assertEquals(
            new Tuple([new Tuple([])]),
            $this->read('(())')
        );

        $this->assertEquals(
            new Tuple([new Symbol('a')]),
            $this->read('(a)')
        );

        $this->assertEquals(
            new Tuple([new Symbol('a'), new Symbol('b')]),
            $this->read('(a b)')
        );
    }

    public function testRdlistBracket() {
        $this->assertEquals(
            new Tuple([], true),
            $this->read('[]')
        );
        $this->assertEquals(
            new Tuple([new Tuple([], true)], true),
            $this->read('[[]]')
        );

        $this->assertEquals(
            new Tuple([new Symbol('a')], true),
            $this->read('[a]')
        );

        $this->assertEquals(
            new Tuple([new Symbol('a'), new Symbol('b')], true),
            $this->read('[a b]')
        );
    }

    public function testQuote() {
        $this->assertEquals(
            new Tuple([new Symbol('quote'), new Symbol('a')]),
            $this->read('\'a')
        );
    }

    public function testUnquote() {
        $this->assertEquals(
            new Tuple([new Symbol('unquote'), new Symbol('a')]),
            $this->read(',a')
        );
    }

    public function testUnquoteSplice() {
        $this->assertEquals(
            new Tuple([new Symbol('unquote-splicing'), new Symbol('a')]),
            $this->read(',@a')
        );
    }

    public function testQuasiquote1() {
        $this->assertEquals(
            new Tuple([new Symbol('quote'), new Symbol('unquote')]),
            $this->read('`unquote')
        );
    }

    public function testQuasiquote2() {
        $this->assertEquals(
            new Tuple([new Symbol('quote'), new Symbol('a')]),
            $this->read('`a')
        );
    }

    public function testQuasiquote3() {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple (quote foo)) (tuple bar)))'),
            $this->read('`(foo ,bar)')
        );
    }

    public function testQuasiquote4() {
        $this->assertEquals(
            $this->read('\'a'),
            $this->read('``,a')
        );
    }

    public function testQuasiquote5() {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple (quote foo)) bar))'),
            $this->read('`(foo ,@bar)')
        );
    }

    public function testQuasiquote6() {
        $this->assertEquals(
            $this->read('(apply tuple (concat (tuple foo) bar))'),
            $this->read('`(,foo ,@bar)')
        );
    }

    public function testQuasiquote7() {
        $this->assertEquals(
            $this->read('(apply tuple (concat foo bar))'),
            $this->read('`(,@foo ,@bar)')
        );
    }

    public function testQuasiquote8() {
        $this->assertEquals(
            $this->read('(apply tuple (concat foo bar (tuple 1) (tuple "string") (tuple :keyword) (tuple true) (tuple nil)))'),
            $this->read('`(,@foo ,@bar 1 "string" :keyword true nil)')
        );
    }

    public function testReadString() {
        $this->assertEquals(
            new PhelString('abc'),
            $this->read('"abc"')
        );

        $this->assertEquals(
            new PhelString('ab"c'),
            $this->read('"ab\"c"')
        );
    }

    public function readEmptyArray() {
        $this->assertEquals(
            new PhelArray([]),
            $this->read('@[]')
        );
    }

    public function readArray1() {
        $this->assertEquals(
            new PhelArray([new Number(1)]),
            $this->read('@[1]')
        );
    }

    public function readArray2() {
        $this->assertEquals(
            new PhelArray([new Number(1), new Number(2)]),
            $this->read('@[1 2]')
        );
    }

    public function readEmptyTable() {
        $this->assertEquals(
            Table::fromKVs(),
            $this->read('@{}')
        );
    }

    public function readTable1() {
        $this->assertEquals(
            Table::fromKVs(new Keyword('a'), new Number(1)),
            $this->read('@{:a 1}')
        );
    }

    public function readTable2() {
        $this->assertEquals(
            Table::fromKVs(new Keyword('a'), new Number(1), new Keyword('b'), new Number(2)),
            $this->read('@{:a 1 :b 2}')
        );
    }

    public function testTableUneven() {
        $this->expectException(ReaderException::class);
        $this->read('@{:a}');
    }

    public function read($string) {
        $reader = new Reader();
        $stream = new StringCharStream($string);
        
        return $reader->read($stream)->getAst();
    }
}