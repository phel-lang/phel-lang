<?php

namespace Phel\Reader;

use Phel\Exceptions\ReaderException;
use Phel\Lang\Boolean;
use Phel\Lang\Keyword;
use Phel\Lang\Nil;
use Phel\Lang\Number;
use Phel\Lang\Phel;
use Phel\Lang\PhelArray;
use Phel\Lang\PhelString;
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
        $this->assertEquals($this->loc(new Number(1), 1, 1, 1, 1), $this->read('1'));
        $this->assertEquals($this->loc(new Number(10), 1, 1, 1, 2), $this->read('10'));
        $this->assertEquals($this->loc(new Number(1.1), 1, 1, 1, 3), $this->read('1.1'));
        $this->assertEquals($this->loc(new Number(10.11), 1, 1, 1, 5), $this->read('10.11'));
    }

    public function testReadKeyword() {
        $this->assertEquals(
            $this->loc(new Keyword('test'), 1, 1, 1, 5),
            $this->read(':test')
        );
    }

    public function testReadBoolean() {
        $this->assertEquals(
            $this->loc(new Boolean(true), 1, 1, 1, 4), 
            $this->read('true')
        );
        $this->assertEquals(
            $this->loc(new Boolean(false), 1, 1, 1, 5),
            $this->read('false')
        );
    }

    public function testReadNil() {
        $this->assertEquals(
            $this->loc(Nil::getInstance(), 1, 1, 1, 3), 
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
            $this->loc(new PhelString('abc'), 1, 1, 1, 5),
            $this->read('"abc"')
        );

        $this->assertEquals(
            $this->loc(new PhelString('ab"c'), 1, 1, 1, 7),
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

    private function removeLoc(Phel $x) {
        $x->setStartLocation(new SourceLocation('string', 0, 0));
        $x->setEndLocation(new SourceLocation('string', 0, 0));

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