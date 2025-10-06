<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Ast;

use Phel\Compiler\Domain\Analyzer\Ast\InNsNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\SourceLocation;
use PHPUnit\Framework\TestCase;

final class InNsNodeTest extends TestCase
{
    public function test_creates_node_with_namespace(): void
    {
        $node = new InNsNode('app\\main');

        self::assertSame('app\\main', $node->getNamespace());
    }

    public function test_stores_different_namespaces(): void
    {
        $node1 = new InNsNode('app\\module1');
        $node2 = new InNsNode('app\\module2\\domain');

        self::assertSame('app\\module1', $node1->getNamespace());
        self::assertSame('app\\module2\\domain', $node2->getNamespace());
    }

    public function test_has_empty_environment(): void
    {
        $node = new InNsNode('test\\ns');

        self::assertEquals(NodeEnvironment::empty(), $node->getEnv());
    }

    public function test_preserves_source_location(): void
    {
        $sourceLocation = new SourceLocation('test.phel', 1, 0);
        $node = new InNsNode('test\\ns', $sourceLocation);

        self::assertSame($sourceLocation, $node->getStartSourceLocation());
    }

    public function test_source_location_is_optional(): void
    {
        $node = new InNsNode('test\\ns');

        self::assertNull($node->getStartSourceLocation());
    }
}
