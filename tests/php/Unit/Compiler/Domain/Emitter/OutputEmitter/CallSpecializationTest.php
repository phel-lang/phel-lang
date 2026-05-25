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
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SeqInterface;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class CallSpecializationTest extends TestCase
{
    public function test_count_on_typed_vector_specialises_to_method_call(): void
    {
        $node = $this->coreCall('count', [$this->vectorLocal('v')]);

        $spec = CallSpecialization::typedVectorMethodCall($node);

        self::assertSame(['method' => 'count', 'args' => []], $spec);
    }

    public function test_nth_on_typed_vector_specialises_to_get(): void
    {
        $node = $this->coreCall('nth', [$this->vectorLocal('v'), new LiteralNode($this->env(), 0)]);

        $spec = CallSpecialization::typedVectorMethodCall($node);

        self::assertSame(['method' => 'get', 'args' => [1]], $spec);
    }

    public function test_nth_on_untyped_local_falls_back(): void
    {
        $env = $this->env();
        $node = $this->coreCall('nth', [
            new LocalVarNode($env, Symbol::create('v')),
            new LiteralNode($env, 0),
        ]);

        self::assertNull(CallSpecialization::typedVectorMethodCall($node));
    }

    public function test_count_with_wrong_arity_skips(): void
    {
        $node = $this->coreCall('count', [$this->vectorLocal('v'), new LiteralNode($this->env(), 0)]);

        self::assertNull(CallSpecialization::typedVectorMethodCall($node));
    }

    public function test_other_core_fn_on_typed_vector_skips(): void
    {
        $node = $this->coreCall('inc', [$this->vectorLocal('v')]);

        self::assertNull(CallSpecialization::typedVectorMethodCall($node));
    }

    public function test_first_on_typed_seq_specialises_to_first_method(): void
    {
        $node = $this->coreCall('first', [$this->localWithTag('s', SeqInterface::class)]);

        self::assertSame('first', CallSpecialization::typedSeqMethodName($node));
    }

    public function test_rest_on_typed_vector_specialises_to_rest_method(): void
    {
        $node = $this->coreCall('rest', [$this->localWithTag('v', PersistentVectorInterface::class)]);

        self::assertSame('rest', CallSpecialization::typedSeqMethodName($node));
    }

    public function test_first_on_untyped_local_falls_back(): void
    {
        $env = $this->env();
        $node = $this->coreCall('first', [new LocalVarNode($env, Symbol::create('s'))]);

        self::assertNull(CallSpecialization::typedSeqMethodName($node));
    }

    public function test_next_is_not_specialised(): void
    {
        // `next` returns nil on empty rest; a bare method call cannot
        // reproduce that without a runtime probe, so the specialiser
        // stays out of it.
        $node = $this->coreCall('next', [$this->localWithTag('s', SeqInterface::class)]);

        self::assertNull(CallSpecialization::typedSeqMethodName($node));
    }

    public function test_assoc_on_typed_map_specialises_to_put(): void
    {
        $env = $this->env();
        $node = $this->coreCall('assoc', [
            $this->localWithTag('m', Phel\Lang\Collections\Map\PersistentMapInterface::class),
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
            $this->localWithTag('m', Phel\Lang\Collections\Map\PersistentMapInterface::class),
            new LiteralNode($env, 'k'),
        ]);

        self::assertSame('remove', CallSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_variadic_assoc_is_not_specialised(): void
    {
        $env = $this->env();
        $node = $this->coreCall('assoc', [
            $this->localWithTag('m', Phel\Lang\Collections\Map\PersistentMapInterface::class),
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
