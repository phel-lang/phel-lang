<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Lang\SourceLocation;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\NumberNode;
use Phel\Compiler\Token;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ListNodeTest extends TestCase
{
    public function testGetCodeTuple()
    {
        self::assertEquals(
            '(1)',
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeBracket()
    {
        self::assertEquals(
            '[1]',
            (new ListNode(Token::T_OPEN_BRACKET, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeBrace()
    {
        self::assertEquals(
            '{1}',
            (new ListNode(Token::T_OPEN_BRACE, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeFn()
    {
        self::assertEquals(
            '|(1)',
            (new ListNode(Token::T_FN, $this->loc(1, 0), $this->loc(1, 4), [
                new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeTable()
    {
        self::assertEquals(
            '@{1}',
            (new ListNode(Token::T_TABLE, $this->loc(1, 0), $this->loc(1, 4), [
                new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeArray()
    {
        self::assertEquals(
            '@[1]',
            (new ListNode(Token::T_ARRAY, $this->loc(1, 0), $this->loc(1, 4), [
                new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
            ]))->getCode()
        );
    }

    public function testUndefinedToken()
    {
        $this->expectException(RuntimeException::class);
        (new ListNode(300, $this->loc(1, 0), $this->loc(1, 4), [
            new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
        ]))->getCode();
    }

    public function testGetChildren()
    {
        self::assertEquals(
            [new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1)],
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getChildren()
        );
    }

    public function testGetStartLocation()
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getStartLocation()
        );
    }

    public function testGetEndLocation()
    {
        self::assertEquals(
            $this->loc(1, 3),
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getEndLocation()
        );
    }

    private function loc($line, $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
