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
use Phel\Compiler\Domain\Emitter\OutputEmitter\ReduceSpecialization;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SeqInterface;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class ReduceSpecializationTest extends TestCase
{
    public function test_three_arg_reduce_over_typed_vector_is_specialised(): void
    {
        $node = $this->coreCall('reduce', [
            $this->local('f'),
            new LiteralNode($this->env(), 0),
            $this->vectorLocal('v'),
        ]);

        self::assertTrue(ReduceSpecialization::isTypedVectorReduce($node));
    }

    public function test_two_arg_reduce_falls_back(): void
    {
        // `(reduce f coll)` seeds the accumulator from the collection and
        // calls `(f)` on empty input; the foreach lowering only models the
        // explicit-init arity.
        $node = $this->coreCall('reduce', [
            $this->local('f'),
            $this->vectorLocal('v'),
        ]);

        self::assertFalse(ReduceSpecialization::isTypedVectorReduce($node));
    }

    public function test_reduce_over_untyped_collection_falls_back(): void
    {
        $node = $this->coreCall('reduce', [
            $this->local('f'),
            new LiteralNode($this->env(), 0),
            $this->local('v'),
        ]);

        self::assertFalse(ReduceSpecialization::isTypedVectorReduce($node));
    }

    public function test_reduce_over_typed_seq_falls_back(): void
    {
        // A seq may be lazy and infinite; `foreach` over it is still
        // correct, but the tag alone does not guarantee an IteratorAggregate,
        // so the specialiser stays conservative and only fires on vectors.
        $node = $this->coreCall('reduce', [
            $this->local('f'),
            new LiteralNode($this->env(), 0),
            $this->localWithTag('s', SeqInterface::class),
        ]);

        self::assertFalse(ReduceSpecialization::isTypedVectorReduce($node));
    }

    public function test_reduce_over_typed_map_falls_back(): void
    {
        $node = $this->coreCall('reduce', [
            $this->local('f'),
            new LiteralNode($this->env(), 0),
            $this->localWithTag('m', PersistentMapInterface::class),
        ]);

        self::assertFalse(ReduceSpecialization::isTypedVectorReduce($node));
    }

    public function test_other_core_fn_over_typed_vector_falls_back(): void
    {
        $node = $this->coreCall('map', [
            $this->local('f'),
            new LiteralNode($this->env(), 0),
            $this->vectorLocal('v'),
        ]);

        self::assertFalse(ReduceSpecialization::isTypedVectorReduce($node));
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

    private function local(string $name): LocalVarNode
    {
        return new LocalVarNode($this->env(), Symbol::create($name));
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
