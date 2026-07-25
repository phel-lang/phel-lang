<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Parser\Node;

use Phel\Lang\SourceLocation;
use Phel\Shared\Parser\Node\AbstractTriviaNode;
use Phel\Shared\Parser\Node\CommentNode;
use Phel\Shared\Parser\Node\NewlineNode;
use Phel\Shared\Parser\Node\WhitespaceNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * All trivia nodes share `AbstractTriviaNode`, whose whole contract is to hand
 * back the code and locations it was constructed with, so one parameterized
 * case covers every flavour.
 */
final class TriviaNodeTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractTriviaNode>, string, SourceLocation, SourceLocation}>
     */
    public static function provideTriviaNode(): iterable
    {
        yield 'comment' => [
            CommentNode::class,
            '# Test',
            new SourceLocation('string', 1, 0),
            new SourceLocation('string', 1, 6),
        ];

        yield 'newline' => [
            NewlineNode::class,
            '\n',
            new SourceLocation('string', 1, 0),
            new SourceLocation('string', 2, 0),
        ];

        yield 'whitespace' => [
            WhitespaceNode::class,
            ' ',
            new SourceLocation('string', 1, 0),
            new SourceLocation('string', 1, 1),
        ];
    }

    /**
     * @param class-string<AbstractTriviaNode> $nodeClass
     */
    #[DataProvider('provideTriviaNode')]
    public function test_it_keeps_the_code_and_locations_it_was_built_with(
        string $nodeClass,
        string $code,
        SourceLocation $start,
        SourceLocation $end,
    ): void {
        $node = new $nodeClass($code, $start, $end);

        self::assertSame($code, $node->getCode());
        self::assertEquals($start, $node->getStartLocation());
        self::assertEquals($end, $node->getEndLocation());
    }
}
