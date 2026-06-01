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
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class CallSpecializationTest extends TestCase
{
    public function test_assoc_on_typed_map_specialises_to_put(): void
    {
        $env = $this->env();
        $node = $this->coreCall('assoc', [
            $this->localWithTag('m', PersistentMapInterface::class),
            new LiteralNode($env, 'k'),
            new LiteralNode($env, 1),
        ]);

        self::assertSame('put', CallSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_assoc_on_typed_vector_specialises_to_update(): void
    {
        $env = $this->env();
        $node = $this->coreCall('assoc', [
            $this->vectorLocal('v'),
            new LiteralNode($env, 0),
            new LiteralNode($env, 42),
        ]);

        self::assertSame('update', CallSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_conj_on_typed_vector_specialises_to_append(): void
    {
        $env = $this->env();
        $node = $this->coreCall('conj', [
            $this->vectorLocal('v'),
            new LiteralNode($env, 99),
        ]);

        self::assertSame('append', CallSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_dissoc_on_typed_map_specialises_to_remove(): void
    {
        $env = $this->env();
        $node = $this->coreCall('dissoc', [
            $this->localWithTag('m', PersistentMapInterface::class),
            new LiteralNode($env, 'k'),
        ]);

        self::assertSame('remove', CallSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_variadic_assoc_is_not_specialised(): void
    {
        $env = $this->env();
        $node = $this->coreCall('assoc', [
            $this->localWithTag('m', PersistentMapInterface::class),
            new LiteralNode($env, 'k1'),
            new LiteralNode($env, 1),
            new LiteralNode($env, 'k2'),
            new LiteralNode($env, 2),
        ]);

        self::assertNull(CallSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_conj_on_untyped_local_falls_back(): void
    {
        $env = $this->env();
        $node = $this->coreCall('conj', [
            new LocalVarNode($env, Symbol::create('v')),
            new LiteralNode($env, 99),
        ]);

        self::assertNull(CallSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_not_eq_peephole_fires_when_inner_eq_is_typed(): void
    {
        $this->env();
        $inner = $this->coreCall('=', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
        ]);
        $outer = $this->coreCall('not', [$inner]);

        self::assertSame($inner, CallSpecialization::notEqPeepholeInner($outer));
    }

    public function test_not_eq_peephole_skips_when_inner_eq_is_untyped(): void
    {
        $env = $this->env();
        $inner = $this->coreCall('=', [
            new LocalVarNode($env, Symbol::create('a')),
            new LocalVarNode($env, Symbol::create('b')),
        ]);
        $outer = $this->coreCall('not', [$inner]);

        self::assertNull(CallSpecialization::notEqPeepholeInner($outer));
    }

    public function test_not_eq_peephole_skips_when_outer_is_not_not(): void
    {
        $this->env();
        $inner = $this->coreCall('=', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
        ]);
        $outer = $this->coreCall('inc', [$inner]);

        self::assertNull(CallSpecialization::notEqPeepholeInner($outer));
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
            CallSpecialization::typedVariadicChain($node),
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
            CallSpecialization::typedVariadicChain($node),
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

        self::assertNull(CallSpecialization::typedVariadicChain($node));
    }

    public function test_variadic_chain_rejects_two_args(): void
    {
        $node = $this->coreCall('*', [
            $this->localWithTag('a', 'int'),
            $this->localWithTag('b', 'int'),
        ]);

        self::assertNull(CallSpecialization::typedVariadicChain($node));
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

        self::assertNull(CallSpecialization::typedVariadicChain($node));
    }

    public function test_variadic_chain_rejects_untyped_local(): void
    {
        $env = $this->env();
        $node = $this->coreCall('+', [
            $this->localWithTag('a', 'int'),
            new LocalVarNode($env, Symbol::create('b')),
            $this->localWithTag('c', 'int'),
        ]);

        self::assertNull(CallSpecialization::typedVariadicChain($node));
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

    private function vectorLocal(string $name): LocalVarNode
    {
        return $this->localWithTag($name, PersistentVectorInterface::class);
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
