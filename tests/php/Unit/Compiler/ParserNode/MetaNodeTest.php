<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler;

use Phel\Lang\SourceLocation;
use Phel\Compiler\ParserNode\KeywordNode;
use Phel\Compiler\ParserNode\MetaNode;
use Phel\Compiler\ParserNode\SymbolNode;
use Phel\Compiler\ParserNode\WhitespaceNode;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class MetaNodeTest extends TestCase
{
    public function testGetCode()
    {
        self::assertEquals(
            '^:test test',
            (new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), new Keyword('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ]
            ))->getCode()
        );
    }

    public function testGetStartLocation()
    {
        self::assertEquals(
            $this->loc(1, 0),
            (new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), new Keyword('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ]
            ))->getStartLocation()
        );
    }

    public function testGetEndLocation()
    {
        self::assertEquals(
            $this->loc(1, 11),
            (new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), new Keyword('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ]
            ))->getEndLocation()
        );
    }

    public function testGetChildren()
    {
        self::assertEquals(
            [
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), new Keyword('test')),
                new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
            ],
            (new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), new Keyword('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ]
            ))->getChildren()
        );
    }

    private function loc($line, $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
