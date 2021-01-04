<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Parser\ParserNode;

use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\ParserNode\ListNode;
use Phel\Compiler\Parser\ParserNode\NumberNode;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ListNodeTest extends TestCase
{
    public function testGetCodeTuple(): void
    {
        self::assertEquals(
            '(1)',
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeBracket(): void
    {
        self::assertEquals(
            '[1]',
            (new ListNode(Token::T_OPEN_BRACKET, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeBrace(): void
    {
        self::assertEquals(
            '{1}',
            (new ListNode(Token::T_OPEN_BRACE, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeFn(): void
    {
        self::assertEquals(
            '|(1)',
            (new ListNode(Token::T_FN, $this->loc(1, 0), $this->loc(1, 4), [
                new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeTable(): void
    {
        self::assertEquals(
            '@{1}',
            (new ListNode(Token::T_TABLE, $this->loc(1, 0), $this->loc(1, 4), [
                new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
            ]))->getCode()
        );
    }

    public function testGetCodeArray(): void
    {
        self::assertEquals(
            '@[1]',
            (new ListNode(Token::T_ARRAY, $this->loc(1, 0), $this->loc(1, 4), [
                new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
            ]))->getCode()
        );
    }

    public function testUndefinedToken(): void
    {
        $this->expectException(RuntimeException::class);
        (new ListNode(300, $this->loc(1, 0), $this->loc(1, 4), [
            new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
        ]))->getCode();
    }

    public function testGetChildren(): void
    {
        self::assertEquals(
            [new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1)],
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getChildren()
        );
    }

    public function testGetStartLocation(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getStartLocation()
        );
    }

    public function testGetEndLocation(): void
    {
        self::assertEquals(
            $this->loc(1, 3),
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getEndLocation()
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
