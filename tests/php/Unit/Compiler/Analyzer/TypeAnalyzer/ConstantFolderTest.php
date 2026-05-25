<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
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

    public function test_fold_eq_is_type_strict_for_mixed_int_float(): void
    {
        // Phel `=` is type-strict: `(= 1 1.0)` is `false`.
        $folded = new ConstantFolder()->fold($this->coreCall('=', [1, 1.0]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
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

    public function test_fold_not_on_truthy_literal(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('not', [1]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
    }

    public function test_fold_not_on_false_literal(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('not', [false]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_not_on_nil_literal(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('not', [null]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_nil_pred_on_nil(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('nil?', [null]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_nil_pred_on_int(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('nil?', [0]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
    }

    public function test_fold_true_pred_strict_on_true(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('true?', [true]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_true_pred_rejects_truthy_non_true(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('true?', [1]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
    }

    public function test_fold_false_pred_strict_on_false(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('false?', [false]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_false_pred_rejects_falsy_non_false(): void
    {
        // `nil` is falsy in Phel but `(false? nil)` is `false`.
        $folded = new ConstantFolder()->fold($this->coreCall('false?', [null]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
    }

    public function test_fold_boolean_truthy(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('boolean', [0]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_boolean_empty_string_is_truthy(): void
    {
        // Phel: only `nil` / `false` are falsy; `""` is truthy.
        $folded = new ConstantFolder()->fold($this->coreCall('boolean', ['']));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertTrue($folded->getValue());
    }

    public function test_fold_boolean_falsy(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCall('boolean', [null]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertFalse($folded->getValue());
    }

    public function test_fold_bool_predicate_skips_when_arg_count_wrong(): void
    {
        self::assertNull(new ConstantFolder()->fold($this->coreCall('not', [])));
        self::assertNull(new ConstantFolder()->fold($this->coreCall('not', [1, 2])));
    }

    public function test_fold_bool_predicate_skips_non_literal_arg(): void
    {
        $node = new CallNode(
            NodeEnvironment::empty()->withExpressionContext(),
            new GlobalVarNode(
                NodeEnvironment::empty()->withExpressionContext(),
                CompilerConstants::PHEL_CORE_NAMESPACE,
                Symbol::create('not'),
                Phel::map(),
            ),
            [$this->globalVar('x')],
        );

        self::assertNull(new ConstantFolder()->fold($node));
    }

    public function test_fold_bitwise_and(): void
    {
        $folded = new ConstantFolder()->fold($this->phpInfixCall('&', [12, 10]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(8, $folded->getValue());
    }

    public function test_fold_bitwise_or(): void
    {
        $folded = new ConstantFolder()->fold($this->phpInfixCall('|', [12, 10]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(14, $folded->getValue());
    }

    public function test_fold_bitwise_xor(): void
    {
        $folded = new ConstantFolder()->fold($this->phpInfixCall('^', [12, 10]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(6, $folded->getValue());
    }

    public function test_fold_bitwise_not_unary(): void
    {
        $folded = new ConstantFolder()->fold($this->phpInfixCall('~', [5]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(-6, $folded->getValue());
    }

    public function test_fold_bitwise_shift_left(): void
    {
        $folded = new ConstantFolder()->fold($this->phpInfixCall('<<', [1, 3]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(8, $folded->getValue());
    }

    public function test_fold_bitwise_shift_right(): void
    {
        $folded = new ConstantFolder()->fold($this->phpInfixCall('>>', [32, 2]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(8, $folded->getValue());
    }

    public function test_fold_bitwise_variadic_and(): void
    {
        $folded = new ConstantFolder()->fold($this->phpInfixCall('&', [15, 12, 10]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(8, $folded->getValue());
    }

    public function test_fold_bitwise_skips_float_arg(): void
    {
        self::assertNull(new ConstantFolder()->fold($this->phpInfixCall('&', [12, 1.5])));
    }

    public function test_fold_bitwise_skips_negative_shift(): void
    {
        // PHP raises ArithmeticError on negative shift; preserve runtime timing.
        self::assertNull(new ConstantFolder()->fold($this->phpInfixCall('<<', [1, -1])));
        self::assertNull(new ConstantFolder()->fold($this->phpInfixCall('>>', [8, -2])));
    }

    public function test_fold_bitwise_not_skips_wrong_arity(): void
    {
        self::assertNull(new ConstantFolder()->fold($this->phpInfixCall('~', [])));
        self::assertNull(new ConstantFolder()->fold($this->phpInfixCall('~', [1, 2])));
    }

    public function test_fold_bitwise_binary_skips_single_arg(): void
    {
        self::assertNull(new ConstantFolder()->fold($this->phpInfixCall('&', [12])));
    }

    public function test_fold_count_vector(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCallWithArgs('count', [$this->vector([1, 2, 3])]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(3, $folded->getValue());
    }

    public function test_fold_count_map_divides_by_two(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCallWithArgs('count', [$this->map([1, 2, 3, 4])]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(2, $folded->getValue());
    }

    public function test_fold_count_set(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCallWithArgs('count', [$this->set([1, 2, 3])]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(3, $folded->getValue());
    }

    public function test_fold_count_skips_non_collection_arg(): void
    {
        self::assertNull(new ConstantFolder()->fold($this->coreCall('count', [42])));
    }

    public function test_fold_first_returns_first_literal(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCallWithArgs('first', [$this->vector([10, 20, 30])]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(10, $folded->getValue());
    }

    public function test_fold_first_empty_vector_is_nil(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCallWithArgs('first', [$this->vector([])]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertNull($folded->getValue());
    }

    public function test_fold_last_returns_last_literal(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCallWithArgs('last', [$this->vector([10, 20, 30])]));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(30, $folded->getValue());
    }

    public function test_fold_first_skips_non_literal_element(): void
    {
        // Mixed: one non-literal child blocks the fold so side effects stay.
        $env = NodeEnvironment::empty()->withExpressionContext();
        $args = [
            new LiteralNode($env, 10),
            $this->globalVar('side-effect'),
        ];
        $vec = new VectorNode($env, $args);
        $node = $this->coreCallWithArgs('first', [$vec]);

        self::assertNull(new ConstantFolder()->fold($node));
    }

    public function test_fold_nth_in_bounds_returns_element(): void
    {
        $folded = new ConstantFolder()->fold($this->coreCallWithArgs(
            'nth',
            [$this->vector([10, 20, 30]), $this->literal(1)],
        ));

        self::assertInstanceOf(LiteralNode::class, $folded);
        self::assertSame(20, $folded->getValue());
    }

    public function test_fold_nth_out_of_bounds_skips(): void
    {
        // Phel raises at runtime; preserve that timing.
        self::assertNull(new ConstantFolder()->fold($this->coreCallWithArgs(
            'nth',
            [$this->vector([10, 20]), $this->literal(99)],
        )));
    }

    public function test_fold_nth_negative_index_skips(): void
    {
        self::assertNull(new ConstantFolder()->fold($this->coreCallWithArgs(
            'nth',
            [$this->vector([10, 20]), $this->literal(-1)],
        )));
    }

    public function test_fold_nth_non_literal_index_skips(): void
    {
        self::assertNull(new ConstantFolder()->fold($this->coreCallWithArgs(
            'nth',
            [$this->vector([10, 20]), $this->globalVar('i')],
        )));
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

    /**
     * @param list<bool|float|int|string|null> $args
     */
    private function phpInfixCall(string $op, array $args): CallNode
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $argNodes = [];
        foreach ($args as $arg) {
            $argNodes[] = new LiteralNode($env, $arg);
        }

        return new CallNode($env, new PhpVarNode($env, $op), $argNodes);
    }

    /**
     * @param list<Phel\Compiler\Domain\Analyzer\Ast\AbstractNode> $argNodes
     */
    private function coreCallWithArgs(string $name, array $argNodes): CallNode
    {
        $env = NodeEnvironment::empty()->withExpressionContext();

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

    /**
     * @param list<bool|float|int|string|null> $values
     */
    private function vector(array $values): VectorNode
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $nodes = [];
        foreach ($values as $v) {
            $nodes[] = new LiteralNode($env, $v);
        }

        return new VectorNode($env, $nodes);
    }

    /**
     * @param list<bool|float|int|string|null> $values
     */
    private function set(array $values): SetNode
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $nodes = [];
        foreach ($values as $v) {
            $nodes[] = new LiteralNode($env, $v);
        }

        return new SetNode($env, $nodes);
    }

    /**
     * Flat `[k0, v0, k1, v1, ...]` list of literals mirroring MapNode shape.
     *
     * @param list<bool|float|int|string|null> $flat
     */
    private function map(array $flat): MapNode
    {
        $env = NodeEnvironment::empty()->withExpressionContext();
        $nodes = [];
        foreach ($flat as $v) {
            $nodes[] = new LiteralNode($env, $v);
        }

        return new MapNode($env, $nodes);
    }

    private function literal(bool|float|int|string|null $value): LiteralNode
    {
        return new LiteralNode(NodeEnvironment::empty()->withExpressionContext(), $value);
    }
}
