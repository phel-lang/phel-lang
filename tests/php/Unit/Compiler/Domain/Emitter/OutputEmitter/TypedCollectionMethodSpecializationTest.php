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
use Phel\Compiler\Domain\Emitter\OutputEmitter\TypedCollectionMethodSpecialization;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SeqInterface;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class TypedCollectionMethodSpecializationTest extends TestCase
{
    public function test_count_on_typed_vector_specialises_to_method_call(): void
    {
        $node = $this->coreCall('count', [$this->vectorLocal('v')]);

        $spec = TypedCollectionMethodSpecialization::typedVectorMethodCall($node);

        self::assertSame(['method' => 'count', 'args' => []], $spec);
    }

    public function test_nth_on_typed_vector_specialises_to_get(): void
    {
        $node = $this->coreCall('nth', [$this->vectorLocal('v'), new LiteralNode($this->env(), 0)]);

        $spec = TypedCollectionMethodSpecialization::typedVectorMethodCall($node);

        self::assertSame(['method' => 'get', 'args' => [1]], $spec);
    }

    public function test_nth_on_untyped_local_falls_back(): void
    {
        $env = $this->env();
        $node = $this->coreCall('nth', [
            new LocalVarNode($env, Symbol::create('v')),
            new LiteralNode($env, 0),
        ]);

        self::assertNull(TypedCollectionMethodSpecialization::typedVectorMethodCall($node));
    }

    public function test_count_with_wrong_arity_skips(): void
    {
        $node = $this->coreCall('count', [$this->vectorLocal('v'), new LiteralNode($this->env(), 0)]);

        self::assertNull(TypedCollectionMethodSpecialization::typedVectorMethodCall($node));
    }

    public function test_other_core_fn_on_typed_vector_skips(): void
    {
        $node = $this->coreCall('inc', [$this->vectorLocal('v')]);

        self::assertNull(TypedCollectionMethodSpecialization::typedVectorMethodCall($node));
    }

    public function test_first_on_typed_seq_specialises_to_first_method(): void
    {
        $node = $this->coreCall('first', [$this->localWithTag('s', SeqInterface::class)]);

        self::assertSame('first', TypedCollectionMethodSpecialization::typedSeqMethodName($node));
    }

    public function test_rest_on_typed_vector_specialises_to_rest_method(): void
    {
        $node = $this->coreCall('rest', [$this->localWithTag('v', PersistentVectorInterface::class)]);

        self::assertSame('rest', TypedCollectionMethodSpecialization::typedSeqMethodName($node));
    }

    public function test_first_on_untyped_local_falls_back(): void
    {
        $env = $this->env();
        $node = $this->coreCall('first', [new LocalVarNode($env, Symbol::create('s'))]);

        self::assertNull(TypedCollectionMethodSpecialization::typedSeqMethodName($node));
    }

    public function test_next_is_not_specialised(): void
    {
        // `next` returns nil on empty rest; a bare method call cannot
        // reproduce that without a runtime probe, so the specialiser
        // stays out of it.
        $node = $this->coreCall('next', [$this->localWithTag('s', SeqInterface::class)]);

        self::assertNull(TypedCollectionMethodSpecialization::typedSeqMethodName($node));
    }

    /**
     * @param list<AbstractNode> $args
     */
    private function coreCall(string $name, array $args): CallNode
    {
        return new CallNode(
            $this->env(),
            new GlobalVarNode($this->env(), CompilerConstants::PHEL_CORE_NAMESPACE, Symbol::create($name), Phel::map()),
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

    private function env(): NodeEnvironment
    {
        return NodeEnvironment::empty()->withExpressionContext();
    }
}
