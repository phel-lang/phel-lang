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
use Phel\Compiler\Domain\Emitter\OutputEmitter\AssocConjSpecialization;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class AssocConjSpecializationTest extends TestCase
{
    public function test_assoc_on_typed_map_specialises_to_put(): void
    {
        $env = $this->env();
        $node = $this->coreCall('assoc', [
            $this->localWithTag('m', PersistentMapInterface::class),
            new LiteralNode($env, 'k'),
            new LiteralNode($env, 1),
        ]);

        self::assertSame('put', AssocConjSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_assoc_on_typed_vector_specialises_to_update(): void
    {
        $env = $this->env();
        $node = $this->coreCall('assoc', [
            $this->vectorLocal('v'),
            new LiteralNode($env, 0),
            new LiteralNode($env, 42),
        ]);

        self::assertSame('update', AssocConjSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_conj_on_typed_vector_specialises_to_append(): void
    {
        $env = $this->env();
        $node = $this->coreCall('conj', [
            $this->vectorLocal('v'),
            new LiteralNode($env, 99),
        ]);

        self::assertSame('append', AssocConjSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_dissoc_on_typed_map_specialises_to_remove(): void
    {
        $env = $this->env();
        $node = $this->coreCall('dissoc', [
            $this->localWithTag('m', PersistentMapInterface::class),
            new LiteralNode($env, 'k'),
        ]);

        self::assertSame('remove', AssocConjSpecialization::typedAssocConjDissocMethod($node));
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

        self::assertNull(AssocConjSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_conj_on_untyped_local_falls_back(): void
    {
        $env = $this->env();
        $node = $this->coreCall('conj', [
            new LocalVarNode($env, Symbol::create('v')),
            new LiteralNode($env, 99),
        ]);

        self::assertNull(AssocConjSpecialization::typedAssocConjDissocMethod($node));
    }

    public function test_assoc_chain_of_two_batches_into_groups(): void
    {
        $env = $this->env();
        $map = $this->localWithTag('m', PersistentMapInterface::class);
        $inner = $this->coreCall('assoc', [$map, new LiteralNode($env, 'a'), new LiteralNode($env, 1)]);
        $outer = $this->coreCall('assoc', [$inner, new LiteralNode($env, 'b'), new LiteralNode($env, 2)]);

        $chain = AssocConjSpecialization::assocConjChain($outer);

        self::assertNotNull($chain);
        self::assertSame($map, $chain['target']);
        self::assertSame('put', $chain['method']);
        self::assertCount(2, $chain['groups']);
    }

    public function test_single_assoc_is_not_treated_as_a_chain(): void
    {
        $env = $this->env();
        $node = $this->coreCall('assoc', [
            $this->localWithTag('m', PersistentMapInterface::class),
            new LiteralNode($env, 'k'),
            new LiteralNode($env, 1),
        ]);

        self::assertNull(AssocConjSpecialization::assocConjChain($node));
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
