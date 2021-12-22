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
    public function test_get_code_list(): void
    {
        self::assertEquals(
            '(1)',
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function test_get_code_bracket(): void
    {
        self::assertEquals(
            '[1]',
            (new ListNode(Token::T_OPEN_BRACKET, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function test_get_code_brace(): void
    {
        self::assertEquals(
            '{1}',
            (new ListNode(Token::T_OPEN_BRACE, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getCode()
        );
    }

    public function test_get_code_fn(): void
    {
        self::assertEquals(
            '|(1)',
            (new ListNode(Token::T_FN, $this->loc(1, 0), $this->loc(1, 4), [
                new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
            ]))->getCode()
        );
    }

    public function test_undefined_token(): void
    {
        $this->expectException(RuntimeException::class);
        (new ListNode(300, $this->loc(1, 0), $this->loc(1, 4), [
            new NumberNode('1', $this->loc(1, 2), $this->loc(1, 3), 1),
        ]))->getCode();
    }

    public function test_get_children(): void
    {
        self::assertEquals(
            [new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1)],
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getChildren()
        );
    }

    public function test_get_start_location(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new ListNode(Token::T_OPEN_PARENTHESIS, $this->loc(1, 0), $this->loc(1, 3), [
                new NumberNode('1', $this->loc(1, 1), $this->loc(1, 2), 1),
            ]))->getStartLocation()
        );
    }

    public function test_get_end_location(): void
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
