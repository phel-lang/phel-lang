<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\ConstantHoisting;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\LocalVarReferences;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class LocalVarReferencesTest extends TestCase
{
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
