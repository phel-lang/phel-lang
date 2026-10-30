<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Parser\Node;

use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\KeywordNode;
use Phel\Shared\Parser\Node\MetaNode;
use Phel\Shared\Parser\Node\SymbolNode;
use Phel\Shared\Parser\Node\WhitespaceNode;
use PHPUnit\Framework\TestCase;

final class MetaNodeTest extends TestCase
{
    public function test_legacy_serialized_node_has_empty_prefix_trivia(): void
    {
        // Serialized by the node implementation before prefix trivia (903500215).
        $payload = 'H4sIAAAAAAAC/5VSQW6DMBDkKRUvCAaaaDnlnkKkVOqFi2vcsJKxI9tElSL+XpvSNG0JhdtKnpmdHU8BMYFwX3NRHmqqeVXuqTZcl7mqePnELfVDCAlcDMQbCIP/sEHjhjArII7uC+cobnSTxwnd7auxmjK7tarp9ZknZs4NhBKFn9J0Cd9Yqu1OMWpRSW+UJIPRHZXH8qBazfj1fbh89bVhBBO8oegduTPcLpTH3t4kRaB0FITII8kUkinRNtJjVw6bRFNY/n6i0rip0HhER8qzzsUTL4mHy+o7HA2JX7peInCmouXD6knmtTG/fmRYms6hjrklc4isRlFp7lgUCFx8ukVf8HvMlxotNyfK+Gdz4765mxnJPGs8I/3RXfeLD31z18v4o0nNqv+NyJ/MOt9DDSTrug/wcPAAEAQAAA==';
        $node = unserialize(gzdecode(base64_decode($payload)));

        self::assertInstanceOf(MetaNode::class, $node);
        self::assertSame('^nil nil', $node->getCode());
    }

    public function test_get_code(): void
    {
        self::assertSame(
            '^:test test',
            new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ],
            )->getCode(),
        );
    }

    public function test_get_start_location(): void
    {
        self::assertEquals(
            $this->loc(1, 0),
            new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ],
            )->getStartLocation(),
        );
    }

    public function test_get_end_location(): void
    {
        self::assertEquals(
            $this->loc(1, 11),
            new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ],
            )->getEndLocation(),
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
            new MetaNode(
                new KeywordNode(':test', $this->loc(1, 1), $this->loc(1, 6), Keyword::create('test')),
                $this->loc(1, 0),
                $this->loc(1, 11),
                [
                    new WhitespaceNode(' ', $this->loc(1, 6), $this->loc(1, 7)),
                    new SymbolNode('test', $this->loc(1, 7), $this->loc(1, 11), Symbol::create('test')),
                ],
            )->getChildren(),
        );
    }

    private function loc(int $line, int $column): SourceLocation
    {
        return new SourceLocation('string', $line, $column);
    }
}
