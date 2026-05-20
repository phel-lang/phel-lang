<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\ConstantHoisting;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\PureLiteralDetector;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class PureLiteralDetectorTest extends TestCase
{
    public function test_literal_is_pure(): void
    {
        $node = new LiteralNode(NodeEnvironment::empty(), 1);
        self::assertTrue(PureLiteralDetector::isPure($node));
    }

    public function test_quote_is_pure(): void
    {
        $node = new QuoteNode(NodeEnvironment::empty(), Symbol::create('x'));
        self::assertTrue(PureLiteralDetector::isPure($node));
    }

    public function test_vector_of_literals_is_pure(): void
    {
        $node = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
            new LiteralNode(NodeEnvironment::empty(), 2),
        ]);
        self::assertTrue(PureLiteralDetector::isPure($node));
    }

    public function test_nested_vector_of_literals_is_pure(): void
    {
        $inner = new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);
        $outer = new VectorNode(NodeEnvironment::empty(), [$inner]);
        self::assertTrue(PureLiteralDetector::isPure($outer));
    }

    public function test_map_of_literals_is_pure(): void
    {
        $node = new MapNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'k'),
            new LiteralNode(NodeEnvironment::empty(), 'v'),
        ]);
        self::assertTrue(PureLiteralDetector::isPure($node));
    }

    public function test_set_of_literals_is_pure(): void
    {
        $node = new SetNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);
        self::assertTrue(PureLiteralDetector::isPure($node));
    }

    public function test_vector_containing_a_call_is_not_pure(): void
    {
        $call = new CallNode(
            NodeEnvironment::empty(),
            new GlobalVarNode(NodeEnvironment::empty(), 'phel.core', Symbol::create('inc'), Phel::map()),
            [new LiteralNode(NodeEnvironment::empty(), 1)],
        );
        $node = new VectorNode(NodeEnvironment::empty(), [$call]);

        self::assertFalse(PureLiteralDetector::isPure($node));
    }

    public function test_map_with_dynamic_value_is_not_pure(): void
    {
        $call = new CallNode(
            NodeEnvironment::empty(),
            new GlobalVarNode(NodeEnvironment::empty(), 'phel.core', Symbol::create('inc'), Phel::map()),
            [],
        );
        $node = new MapNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'k'),
            $call,
        ]);

        self::assertFalse(PureLiteralDetector::isPure($node));
    }

    public function test_call_node_is_not_pure(): void
    {
        $node = new CallNode(
            NodeEnvironment::empty(),
            new GlobalVarNode(NodeEnvironment::empty(), 'phel.core', Symbol::create('inc'), Phel::map()),
            [],
        );
        self::assertFalse(PureLiteralDetector::isPure($node));
    }
}
