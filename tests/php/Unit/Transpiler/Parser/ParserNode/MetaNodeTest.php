<?php

declare(strict_types=1);

namespace PhelTest\Unit\Transpiler\Parser\ParserNode;

use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Transpiler\Domain\Parser\ParserNode\KeywordNode;
use Phel\Transpiler\Domain\Parser\ParserNode\MetaNode;
use Phel\Transpiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Transpiler\Domain\Parser\ParserNode\WhitespaceNode;
use PHPUnit\Framework\TestCase;

final class MetaNodeTest extends TestCase
{
    public function test_get_code(): void
    {
        self::assertSame(
            '^:test test',
            (new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ],
            ))->getCode(),
        );
    }

    public function test_get_start_location(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ],
            ))->getStartLocation(),
        );
    }

    public function test_get_end_location(): void
    {
        self::assertEquals(
            $this->loc(1, 11),
            (new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ],
            ))->getEndLocation(),
        );
    }

    public function test_get_children(): void
    {
        self::assertEquals(
            [
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
            ],
            (new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ],
            ))->getChildren(),
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
