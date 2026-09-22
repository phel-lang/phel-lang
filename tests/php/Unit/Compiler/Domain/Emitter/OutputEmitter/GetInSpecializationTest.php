<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitter\GetInSpecialization;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class GetInSpecializationTest extends TestCase
{
    public function test_untagged_target_with_a_literal_path_is_lowered(): void
    {
        $keys = [$this->literal('a'), $this->literal('b')];
        $node = $this->coreCall('get-in', [$this->local('m'), $this->path($keys)]);

        self::assertSame($keys, GetInSpecialization::literalPathKeys($node));
        self::assertNull(GetInSpecialization::subscriptChainKeys($node), 'no subscript chain without a tag');
        self::assertTrue(CallSpecialization::isSpecialized($node));
    }

    public function test_the_default_arity_is_lowered(): void
    {
        $node = $this->coreCall('get-in', [$this->local('m'), $this->path([$this->literal('a')]), $this->literal('nf')]);

        self::assertNotNull(GetInSpecialization::literalPathKeys($node));
    }

    public function test_an_empty_literal_path_is_lowered(): void
    {
        $node = $this->coreCall('get-in', [$this->local('m'), $this->path([])]);

        self::assertSame([], GetInSpecialization::literalPathKeys($node));
    }

    public function test_a_tagged_target_keeps_the_subscript_chain(): void
    {
        $keys = [$this->literal('a')];
        $node = $this->coreCall('get-in', [$this->localWithTag('m', PersistentMapInterface::class), $this->path($keys)]);

        self::assertSame($keys, GetInSpecialization::subscriptChainKeys($node));
    }

    public function test_a_path_that_is_not_a_vector_literal_keeps_the_runtime_call(): void
    {
        $node = $this->coreCall('get-in', [$this->local('m'), $this->local('p')]);

        self::assertNull(GetInSpecialization::literalPathKeys($node));
        self::assertFalse(CallSpecialization::isSpecialized($node));
    }

    /**
     * Metadata on the path is a form of its own, evaluated with the vector.
     * Lowering would drop it, so the call keeps the runtime path.
     */
    public function test_a_path_with_metadata_keeps_the_runtime_call(): void
    {
        $env = $this->env();
        $path = new VectorNode($env, [$this->literal('a')], null, new MapNode($env, []));
        $node = $this->coreCall('get-in', [$this->local('m'), $path]);

        self::assertNull(GetInSpecialization::literalPathKeys($node));
    }

    public function test_wrong_arity_keeps_the_runtime_call(): void
    {
        $path = $this->path([$this->literal('a')]);

        self::assertNull(GetInSpecialization::literalPathKeys($this->coreCall('get-in', [$this->local('m')])));
        self::assertNull(GetInSpecialization::literalPathKeys(
            $this->coreCall('get-in', [$this->local('m'), $path, $this->literal(1), $this->literal(2)]),
        ));
    }

    public function test_a_locally_bound_get_in_keeps_the_call(): void
    {
        $env = $this->env();
        $node = new CallNode($env, new LocalVarNode($env, Symbol::create('get-in')), [
            $this->local('m'),
            $this->path([$this->literal('a')]),
        ]);

        self::assertNull(GetInSpecialization::literalPathKeys($node));
    }

    public function test_another_core_fn_is_not_lowered(): void
    {
        $node = $this->coreCall('get', [$this->local('m'), $this->path([$this->literal('a')])]);

        self::assertNull(GetInSpecialization::literalPathKeys($node));
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

    /**
     * @param list<AbstractNode> $keys
     */
    private function path(array $keys): VectorNode
    {
        return new VectorNode($this->env(), $keys);
    }

    private function literal(mixed $value): LiteralNode
    {
        return new LiteralNode($this->env(), $value);
    }

    private function local(string $name): LocalVarNode
    {
        return new LocalVarNode($this->env(), Symbol::create($name));
    }

    private function localWithTag(string $name, string $tag): LocalVarNode
    {
        $sym = Symbol::create($name);
        $locals = [$sym->withMeta(Phel::map(Keyword::create('tag'), $tag))];

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
