<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NumericOperationSpecialization;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

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
