<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\ConstantFolder;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class ConstantFolderTest extends TestCase
{
    public function test_fold_adds_two_integer_literals(): void
    {
        $node = $this->coreCall('+', [1, 2]);

        $folded = new ConstantFolder()->fold($node);

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(3, $folded->getValue());
    }

    public function test_fold_returns_identity_for_empty_plus(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('+', []));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(0, $folded->getValue());
    }

    public function test_fold_returns_identity_for_empty_mul(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('*', []));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(1, $folded->getValue());
    }

    public function test_fold_unary_minus_negates(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('-', [5]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(-5, $folded->getValue());
    }

    public function test_fold_promotes_to_float_when_any_arg_is_float(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('+', [1, 0.5]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(1.5, $folded->getValue());
    }

    public function test_fold_skips_when_any_arg_is_not_literal(): void
    {
        $node = new CallNode(
            NodeEnvironment::empty()->withExpressionContext(),
            $this->globalVar('+'),
            [
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 1),
                $this->globalVar('x'),
            ],
        );

        self::assertNull(new ConstantFolder()->fold($node));
    }

    public function test_fold_skips_when_callee_is_not_in_phel_core(): void
    {
        $node = new CallNode(
            NodeEnvironment::empty()->withExpressionContext(),
            new GlobalVarNode(
                NodeEnvironment::empty(),
                'user',
                Symbol::create('add'),
                Phel::map(),
            ),
            [
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 1),
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 2),
            ],
        );

        self::assertNull(new ConstantFolder()->fold($node));
    }

    public function test_fold_eq_two_equal_int_literals(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('=', [1, 1]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_eq_two_unequal_int_literals(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('=', [1, 2]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
    }

    public function test_fold_eq_mixed_int_float_compares_numerically(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('=', [1, 1.0]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_eq_variadic_all_equal(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('=', [3, 3, 3]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_eq_variadic_one_unequal(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('=', [3, 3, 4]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
    }

    public function test_fold_lt_strict_ascending(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('<', [1, 2, 3]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_lt_equal_pair_is_false(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('<', [1, 1]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
    }

    public function test_fold_lte_non_strict(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('<=', [1, 1, 2]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_gt_descending(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('>', [3, 2, 1]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_gte_non_strict(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('>=', [3, 3, 2]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_not_eq_negates_equality(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('not=', [1, 2]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_comparison_single_arg_is_true(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('<', [42]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_comparison_skips_zero_args(): void
    {
        // (=) is a runtime arity error; preserve that timing.
        self::assertNull(new ConstantFolder()->fold($this->coreCall('=', [])));
    }

    public function test_fold_comparison_skips_when_any_arg_not_literal(): void
    {
        $node = new CallNode(
            NodeEnvironment::empty()->withExpressionContext(),
            new GlobalVarNode(
                NodeEnvironment::empty()->withExpressionContext(),
                CompilerConstants::PHEL_CORE_NAMESPACE,
                Symbol::create('='),
                Phel::map(),
            ),
            [
                new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), 1),
                $this->globalVar('x'),
            ],
        );

        self::assertNull(new ConstantFolder()->fold($node));
    }

    public function test_fold_if_truthy_test_returns_then_branch(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $then = new LiteralNode($env, 'then');
        $else = new LiteralNode($env, 'else');
        $node = new IfNode($env, new LiteralNode($env, true), $then, $else);

        self::assertSame($then, new ConstantFolder()->foldIf($node));
    }

    public function test_fold_if_nil_test_returns_else_branch(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $then = new LiteralNode($env, 'then');
        $else = new LiteralNode($env, 'else');
        $node = new IfNode($env, new LiteralNode($env, null), $then, $else);

        self::assertSame($else, new ConstantFolder()->foldIf($node));
    }

    public function test_fold_if_keeps_node_when_test_is_not_literal(): void
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $node = new IfNode(
            $env,
            $this->globalVar('x'),
            new LiteralNode($env, 'then'),
            new LiteralNode($env, 'else'),
        );

        self::assertNull(new ConstantFolder()->foldIf($node));
    }

    /**
     * @param list<bool|float|int|string|null> $args
     */
    private function coreCall(string $name, array $args): CallNode
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $argNodes = [];
        foreach ($args as $arg) {
            $argNodes[] = new LiteralNode($env, $arg);
        }

        return new CallNode(
            $env,
            new GlobalVarNode(
                $env,
                CompilerConstants::PHEL_CORE_NAMESPACE,
                Symbol::create($name),
                Phel::map(),
            ),
            $argNodes,
        );
    }

    private function globalVar(string $name): GlobalVarNode
    {
        return new GlobalVarNode(
            NodeEnvironment::empty()->withExpressionContext(),
            'user',
            Symbol::create($name),
            Phel::map(),
        );
    }
}
