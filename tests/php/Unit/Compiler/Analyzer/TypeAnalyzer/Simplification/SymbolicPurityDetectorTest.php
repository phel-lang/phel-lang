<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\Simplification;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification\SymbolicPurityDetector;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class SymbolicPurityDetectorTest extends TestCase
{
    private SymbolicPurityDetector $detector;

    private NodeEnvironment $env;

    protected function setUp(): void
    {
        $this->detector = new SymbolicPurityDetector();
        $this->env = NodeEnvironment::empty()->withExpressionContext();
    }

    public function test_literal_is_pure(): void
    {
        self::assertTrue($this->detector->isPure(new LiteralNode($this->env, 42)));
    }

    public function test_local_var_is_pure(): void
    {
        self::assertTrue($this->detector->isPure(new LocalVarNode($this->env, Symbol::create('x'))));
    }

    public function test_global_var_reference_is_pure(): void
    {
        $node = new GlobalVarNode($this->env, 'user', Symbol::create('my-fn'), Phel::map());

        self::assertTrue($this->detector->isPure($node));
    }

    public function test_core_arithmetic_over_free_var_is_pure(): void
    {
        // `(+ x 1)` — the case PureExpressionDetector reports impure
        // because it cannot fold a free variable. Structurally it is pure.
        self::assertTrue($this->detector->isPure($this->coreCall('+', [
            new LocalVarNode($this->env, Symbol::create('x')),
            new LiteralNode($this->env, 1),
        ])));
    }

    public function test_nested_pure_calls_are_pure(): void
    {
        // `(* (+ x 1) 2)`
        $inner = $this->coreCall('+', [
            new LocalVarNode($this->env, Symbol::create('x')),
            new LiteralNode($this->env, 1),
        ]);

        self::assertTrue($this->detector->isPure($this->coreCall('*', [
            $inner,
            new LiteralNode($this->env, 2),
        ])));
    }

    public function test_pure_if_branches_are_pure(): void
    {
        $node = new IfNode(
            $this->env,
            $this->coreCall('<', [new LocalVarNode($this->env, Symbol::create('x')), new LiteralNode($this->env, 0)]),
            new LiteralNode($this->env, 'neg'),
            new LocalVarNode($this->env, Symbol::create('x')),
        );

        self::assertTrue($this->detector->isPure($node));
    }

    public function test_php_infix_operator_is_pure(): void
    {
        $node = new CallNode($this->env, new PhpVarNode($this->env, '+'), [
            new LocalVarNode($this->env, Symbol::create('x')),
            new LiteralNode($this->env, 1),
        ]);

        self::assertTrue($this->detector->isPure($node));
    }

    public function test_side_effecting_core_call_is_impure(): void
    {
        self::assertFalse($this->detector->isPure($this->coreCall('println', [
            new LocalVarNode($this->env, Symbol::create('x')),
        ])));
    }

    public function test_user_call_is_impure(): void
    {
        $node = new CallNode(
            $this->env,
            new GlobalVarNode($this->env, 'user', Symbol::create('add'), Phel::map()),
            [new LiteralNode($this->env, 1)],
        );

        self::assertFalse($this->detector->isPure($node));
    }

    public function test_pure_call_with_impure_argument_is_impure(): void
    {
        // `(+ (println x) 1)` — operator is pure but an argument is not.
        $node = $this->coreCall('+', [
            $this->coreCall('println', [new LocalVarNode($this->env, Symbol::create('x'))]),
            new LiteralNode($this->env, 1),
        ]);

        self::assertFalse($this->detector->isPure($node));
    }

    public function test_php_assignment_operator_is_impure(): void
    {
        $node = new CallNode($this->env, new PhpVarNode($this->env, '='), [
            new LocalVarNode($this->env, Symbol::create('x')),
            new LiteralNode($this->env, 1),
        ]);

        self::assertFalse($this->detector->isPure($node));
    }

    public function test_unhandled_node_type_is_impure(): void
    {
        self::assertFalse($this->detector->isPure(new VectorNode($this->env, [])));
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function coreCall(string $name, array $args): CallNode
    {
        return new CallNode(
            $this->env,
            new GlobalVarNode($this->env, CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create($name), Phel::map()),
            $args,
        );
    }
}
