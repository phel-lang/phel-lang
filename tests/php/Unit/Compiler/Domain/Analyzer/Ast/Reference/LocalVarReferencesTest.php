<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Analyzer\Ast\Reference;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\Reference\LocalVarReferences;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class LocalVarReferencesTest extends TestCase
{
    public function test_local_inside_method_call_is_collected(): void
    {
        // Mirrors `(-> more (get i))` from the `aset` macro body: a
        // local nested in a `MethodCallNode` must still be reachable.
        $methodCall = new MethodCallNode(
            NodeEnvironment::empty(),
            Symbol::create('get'),
            [new LocalVarNode(NodeEnvironment::empty(), Symbol::create('i'))],
        );
        $objCall = new PhpObjectCallNode(
            NodeEnvironment::empty(),
            new LocalVarNode(NodeEnvironment::empty(), Symbol::create('more')),
            $methodCall,
            isStatic: false,
            isMethodCall: true,
        );

        self::assertSame(['more', 'i'], LocalVarReferences::collect($objCall));
    }

    public function test_collects_single_local_var(): void
    {
        $node = new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x'));

        self::assertSame(['x'], LocalVarReferences::collect($node));
    }

    public function test_collects_no_locals_from_a_literal(): void
    {
        $node = new LiteralNode(NodeEnvironment::empty(), 1);

        self::assertSame([], LocalVarReferences::collect($node));
    }

    public function test_collects_locals_in_call_arguments(): void
    {
        $call = new CallNode(
            NodeEnvironment::empty(),
            new PhpVarNode(NodeEnvironment::empty(), '+'),
            [
                new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x')),
                new LocalVarNode(NodeEnvironment::empty(), Symbol::create('y')),
            ],
        );

        self::assertSame(['x', 'y'], LocalVarReferences::collect($call));
    }

    public function test_collects_nested_references(): void
    {
        $inner = new CallNode(
            NodeEnvironment::empty(),
            new PhpVarNode(NodeEnvironment::empty(), '-'),
            [
                new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x')),
                new LiteralNode(NodeEnvironment::empty(), 1),
            ],
        );
        $outer = new CallNode(
            NodeEnvironment::empty(),
            new PhpVarNode(NodeEnvironment::empty(), '+'),
            [
                $inner,
                new LocalVarNode(NodeEnvironment::empty(), Symbol::create('y')),
            ],
        );

        self::assertSame(['x', 'y'], LocalVarReferences::collect($outer));
    }
}
