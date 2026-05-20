<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter\ConstantHoisting;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\BodyConstantScanner;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\ConstantScope;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class BodyConstantScannerTest extends TestCase
{
    public function test_pure_vector_at_root_is_reserved(): void
    {
        $vector = $this->pureVector();
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($vector, $scope);

        self::assertSame(0, $scope->lookup($vector));
        self::assertSame(1, $scope->count());
    }

    public function test_pure_map_at_root_is_reserved(): void
    {
        $map = new MapNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 'k'),
            new LiteralNode(NodeEnvironment::empty(), 'v'),
        ]);
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($map, $scope);

        self::assertSame(0, $scope->lookup($map));
    }

    public function test_pure_set_at_root_is_reserved(): void
    {
        $set = new SetNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
        ]);
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($set, $scope);

        self::assertSame(0, $scope->lookup($set));
    }

    public function test_empty_vector_is_not_reserved(): void
    {
        $empty = new VectorNode(NodeEnvironment::empty(), []);
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($empty, $scope);

        self::assertNull($scope->lookup($empty));
        self::assertSame(0, $scope->count());
    }

    public function test_descends_into_call_arguments(): void
    {
        $vector = $this->pureVector();
        $call = new CallNode(
            NodeEnvironment::empty(),
            new GlobalVarNode(NodeEnvironment::empty(), 'phel.core', Symbol::create('print'), Phel::map()),
            [$vector],
        );
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($call, $scope);

        self::assertSame(0, $scope->lookup($vector));
    }

    public function test_descends_into_if_branches(): void
    {
        $thenVec = $this->pureVector();
        $elseVec = $this->pureVector();
        $if = new IfNode(
            NodeEnvironment::empty(),
            new LiteralNode(NodeEnvironment::empty(), true),
            $thenVec,
            $elseVec,
        );
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($if, $scope);

        self::assertSame(0, $scope->lookup($thenVec));
        self::assertSame(1, $scope->lookup($elseVec));
    }

    public function test_descends_into_let_bindings_and_body(): void
    {
        $initVec = $this->pureVector();
        $bodyVec = $this->pureVector();
        $let = new LetNode(
            NodeEnvironment::empty(),
            [
                new BindingNode(
                    NodeEnvironment::empty(),
                    Symbol::create('x'),
                    Symbol::create('x_1'),
                    $initVec,
                ),
            ],
            $bodyVec,
            false,
        );
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($let, $scope);

        self::assertSame(0, $scope->lookup($initVec));
        self::assertSame(1, $scope->lookup($bodyVec));
    }

    public function test_does_not_descend_into_nested_fn(): void
    {
        $innerVec = $this->pureVector();
        $innerFn = new FnNode(
            NodeEnvironment::empty(),
            [],
            $innerVec,
            [],
            false,
            false,
        );
        $outerDo = new DoNode(NodeEnvironment::empty(), [], $innerFn);
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($outerDo, $scope);

        self::assertNull($scope->lookup($innerVec));
        self::assertSame(0, $scope->count());
    }

    public function test_outer_pure_literal_does_not_recurse_into_children(): void
    {
        $inner = $this->pureVector();
        $outer = new VectorNode(NodeEnvironment::empty(), [$inner]);
        $scope = new ConstantScope();

        new BodyConstantScanner()->scan($outer, $scope);

        // Only the outer literal is hoisted; inner allocates once inside it.
        self::assertSame(0, $scope->lookup($outer));
        self::assertNull($scope->lookup($inner));
        self::assertSame(1, $scope->count());
    }

    private function pureVector(): VectorNode
    {
        return new VectorNode(NodeEnvironment::empty(), [
            new LiteralNode(NodeEnvironment::empty(), 1),
            new LiteralNode(NodeEnvironment::empty(), 2),
            new LiteralNode(NodeEnvironment::empty(), 3),
        ]);
    }
}
