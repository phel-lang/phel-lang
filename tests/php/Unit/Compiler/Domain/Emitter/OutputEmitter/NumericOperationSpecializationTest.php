<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NumericOperationSpecialization;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class NumericOperationSpecializationTest extends TestCase
{
    public function test_not_eq_peephole_fires_when_inner_eq_is_typed(): void
    {
        $this->env();
        $inner = $this->coreCall('=', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
        ]);
        $outer = $this->coreCall('not', [$inner]);

        self::assertSame($inner, NumericOperationSpecialization::notEqPeepholeInner($outer));
    }

    public function test_not_eq_peephole_skips_when_inner_eq_is_untyped(): void
    {
        $env = $this->env();
        $inner = $this->coreCall('=', [
            new LocalVarNode($env, Symbol::create('a')),
            new LocalVarNode($env, Symbol::create('b')),
        ]);
        $outer = $this->coreCall('not', [$inner]);

        self::assertNull(NumericOperationSpecialization::notEqPeepholeInner($outer));
    }

    public function test_not_eq_peephole_skips_when_outer_is_not_not(): void
    {
        $this->env();
        $inner = $this->coreCall('=', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
        ]);
        $outer = $this->coreCall('inc', [$inner]);

        self::assertNull(NumericOperationSpecialization::notEqPeepholeInner($outer));
    }

    public function test_not_over_bool_operand_returns_the_operand(): void
    {
        $operand = $this->boolPhpCall('is_int');
        $outer = $this->coreCall('not', [$operand]);

        self::assertSame($operand, NumericOperationSpecialization::notOverBoolOperand($outer));
        self::assertTrue(NumericOperationSpecialization::isNotOverBoolOperand($outer));
    }

    /**
     * `strlen` returns int, and `!0` is `true` in PHP while `(not 0)` is
     * `false` in Phel. Anything looser than a hard bool guarantee inverts
     * truthiness here.
     */
    public function test_not_over_a_non_bool_php_call_declines(): void
    {
        $outer = $this->coreCall('not', [$this->boolPhpCall('strlen')]);

        self::assertNull(NumericOperationSpecialization::notOverBoolOperand($outer));
    }

    public function test_not_over_an_untyped_local_declines(): void
    {
        $outer = $this->coreCall('not', [new LocalVarNode($this->env(), Symbol::create('x'))]);

        self::assertNull(NumericOperationSpecialization::notOverBoolOperand($outer));
    }

    /**
     * A typed `=` operand is itself a bool, so both lowerings match. The
     * `=` peephole emits `($a !== $b)`, which beats `!(($a === $b))`, so
     * this one must stand down and let the emitter reach it.
     */
    public function test_not_over_bool_yields_to_the_typed_eq_peephole(): void
    {
        $inner = $this->coreCall('=', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
        ]);
        $outer = $this->coreCall('not', [$inner]);

        self::assertNull(NumericOperationSpecialization::notOverBoolOperand($outer));
        self::assertSame($inner, NumericOperationSpecialization::notEqPeepholeInner($outer));
    }

    public function test_not_with_two_arguments_declines(): void
    {
        $outer = $this->coreCall('not', [
            $this->boolPhpCall('is_int'),
            $this->boolPhpCall('is_int'),
        ]);

        self::assertNull(NumericOperationSpecialization::notOverBoolOperand($outer));
    }

    public function test_a_call_that_is_not_not_declines(): void
    {
        $outer = $this->coreCall('inc', [$this->boolPhpCall('is_int')]);

        self::assertNull(NumericOperationSpecialization::notOverBoolOperand($outer));
    }

    public function test_variadic_mul_three_int_locals_chains_arith(): void
    {
        $node = $this->coreCall('*', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
            $this->localWithTag('c', 'int'),
        ]);

        self::assertSame(
            ['op' => '*', 'kind' => 'arith'],
            NumericOperationSpecialization::typedVariadicChain($node),
        );
    }

    public function test_variadic_lt_chains_compare(): void
    {
        $node = $this->coreCall('<', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
            $this->localWithTag('c', 'int'),
        ]);

        self::assertSame(
            ['op' => '<', 'kind' => 'compare'],
            NumericOperationSpecialization::typedVariadicChain($node),
        );
    }

    public function test_variadic_chain_rejects_literal_args(): void
    {
        // Pure-literal int chain must stay on the runtime path so the
        // numeric dispatcher's BigInt / Ratio promotion still triggers.
        $env = $this->env();
        $node = $this->coreCall('*', [
            new LiteralNode($env, 100000000),
            new LiteralNode($env, 100000000),
            new LiteralNode($env, 100000000),
        ]);

        self::assertNull(NumericOperationSpecialization::typedVariadicChain($node));
    }

    public function test_variadic_chain_rejects_two_args(): void
    {
        $node = $this->coreCall('*', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
        ]);

        self::assertNull(NumericOperationSpecialization::typedVariadicChain($node));
    }

    public function test_variadic_chain_rejects_eq_op(): void
    {
        // `=` would need a pairwise `&&` chain like `<`, but it also
        // accepts `bool` / `string` which are not in NUMERIC_PRIMITIVE_TAGS;
        // keep it 2-arg only for now.
        $node = $this->coreCall('=', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
            $this->localWithTag('c', 'int'),
        ]);

        self::assertNull(NumericOperationSpecialization::typedVariadicChain($node));
    }

    public function test_variadic_chain_rejects_untyped_local(): void
    {
        $env = $this->env();
        $node = $this->coreCall('+', [
            $this->localWithTag('a', 'int'),
            new LocalVarNode($env, Symbol::create('b')),
            $this->localWithTag('c', 'int'),
        ]);

        self::assertNull(NumericOperationSpecialization::typedVariadicChain($node));
    }

    public function test_typed_binary_op_skips_two_int_literal_arithmetic(): void
    {
        // `(* 2 <bigint>)` over two int literals only reaches the emitter when
        // the constant folder refused it (native product overflows PHP_INT_MAX).
        // Emitting a native PHP `*` would yield a float, diverging from the
        // runtime's BigInt promotion — keep it on the runtime dispatch.
        $env = $this->env();
        foreach (['+', '-', '*'] as $op) {
            $node = $this->coreCall($op, [
                new LiteralNode($env, 2),
                new LiteralNode($env, 4611686018427387904),
            ]);

            self::assertNull(
                NumericOperationSpecialization::typedBinaryOpName($node),
                sprintf('arithmetic op %s over two int literals must not specialise', $op),
            );
        }
    }

    public function test_typed_binary_op_keeps_literal_mixed_with_typed_local(): void
    {
        // A literal paired with a tagged local is the opt-in case: the user
        // promised the binding is an int, so the native op stays.
        $env = $this->env();
        $node = $this->coreCall('*', [
            $this->localWithTag('a', 'int'),
            new LiteralNode($env, 2),
        ]);

        self::assertSame('*', NumericOperationSpecialization::typedBinaryOpName($node));
    }

    public function test_typed_binary_op_keeps_two_literal_comparison(): void
    {
        // Comparisons cannot overflow, so a two-literal `<` stays specialised.
        $env = $this->env();
        $node = $this->coreCall('<', [
            new LiteralNode($env, 2),
            new LiteralNode($env, 4611686018427387904),
        ]);

        self::assertSame('<', NumericOperationSpecialization::typedBinaryOpName($node));
    }

    public function test_inc_on_int_local_lowers_to_plus(): void
    {
        $node = $this->coreCall('inc', [$this->localWithTag('x', 'int')]);

        self::assertSame('+', NumericOperationSpecialization::typedIncDecOp($node));
    }

    public function test_dec_on_int_local_lowers_to_minus(): void
    {
        $node = $this->coreCall('dec', [$this->localWithTag('x', 'int')]);

        self::assertSame('-', NumericOperationSpecialization::typedIncDecOp($node));
    }

    public function test_inc_on_float_local_lowers_to_plus(): void
    {
        $node = $this->coreCall('inc', [$this->localWithTag('x', 'float')]);

        self::assertSame('+', NumericOperationSpecialization::typedIncDecOp($node));
    }

    public function test_inc_dec_skips_untagged_local(): void
    {
        $env = $this->env();
        $node = $this->coreCall('inc', [new LocalVarNode($env, Symbol::create('x'))]);

        self::assertNull(NumericOperationSpecialization::typedIncDecOp($node));
    }

    public function test_inc_dec_skips_literal_operand(): void
    {
        // An int literal at PHP_INT_MAX would overflow under native `+ 1` and
        // diverge from the runtime BigInt promotion — keep it on dispatch.
        $env = $this->env();
        $node = $this->coreCall('inc', [new LiteralNode($env, 4611686018427387904)]);

        self::assertNull(NumericOperationSpecialization::typedIncDecOp($node));
    }

    public function test_inc_dec_skips_nullable_tag(): void
    {
        // A nullable tag re-admits the `assert-non-nil` guard the runtime
        // defns carry, so the specialisation must not fire.
        $node = $this->coreCall('inc', [$this->localWithTag('x', '?int')]);

        self::assertNull(NumericOperationSpecialization::typedIncDecOp($node));
    }

    public function test_inc_dec_skips_two_arg_form(): void
    {
        $node = $this->coreCall('inc', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
        ]);

        self::assertNull(NumericOperationSpecialization::typedIncDecOp($node));
    }

    private function env(): NodeEnvironment
    {
        return NodeEnvironment::empty()->withExpressionContext();
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function coreCall(string $name, array $args): CallNode
    {
        return new CallNode(
            $this->env(),
            new GlobalVarNode(
                $this->env(),
                CompilerConstants::PHEL_CORE_NAMESPACE,
                Symbol::create($name),
                Phel::map(),
            ),
            $args,
        );
    }

    /**
     * A `(php/<fn> x)` call node. `is_int` is on
     * `BooleanExprDetector::BOOL_PHP_FUNCTIONS`, `strlen` is not, which is
     * what separates the eligible case from the ineligible one.
     */
    private function boolPhpCall(string $phpFunction): CallNode
    {
        return new CallNode(
            $this->env(),
            new PhpVarNode($this->env(), $phpFunction),
            [new LocalVarNode($this->env(), Symbol::create('x'))],
        );
    }

    private function localWithTag(string $name, string $tag): LocalVarNode
    {
        $sym = Symbol::create($name);
        $meta = Phel::map(Keyword::create('tag'), $tag);
        $locals = [$sym->withMeta($meta)];

        $env = NodeEnvironment::empty()
            ->withExpressionContext()
            ->withMergedLocals($locals);

        return new LocalVarNode($env, $sym);
    }
}
