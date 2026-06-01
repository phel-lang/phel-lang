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
use Phel\Compiler\Domain\Emitter\OutputEmitter\TypedValueSpecialization;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use PHPUnit\Framework\TestCase;

final class TypedValueSpecializationTest extends TestCase
{
    public function test_contains_on_array_tagged_target_is_array_kind(): void
    {
        $node = $this->coreCall('contains?', [$this->localWithTag('a', 'array'), new LiteralNode($this->env(), 'k')]);

        self::assertSame('array', TypedValueSpecialization::containsCheckKind($node));
    }

    public function test_contains_on_map_tagged_target_is_method_kind(): void
    {
        $node = $this->coreCall('contains?', [$this->localWithTag('m', PersistentMapInterface::class), new LiteralNode($this->env(), 'k')]);

        self::assertSame('method', TypedValueSpecialization::containsCheckKind($node));
        self::assertTrue(TypedValueSpecialization::isContainsCheck($node));
    }

    public function test_contains_on_hash_set_tagged_target_is_method_kind(): void
    {
        $node = $this->coreCall('contains?', [$this->localWithTag('s', PersistentHashSetInterface::class), new LiteralNode($this->env(), 'k')]);

        self::assertSame('method', TypedValueSpecialization::containsCheckKind($node));
    }

    public function test_contains_on_untyped_target_falls_back(): void
    {
        $node = $this->coreCall('contains?', [$this->local('m'), new LiteralNode($this->env(), 'k')]);

        self::assertNull(TypedValueSpecialization::containsCheckKind($node));
    }

    public function test_empty_fragment_per_tag(): void
    {
        self::assertSame('(%s === [])', TypedValueSpecialization::emptyCheckFragment($this->coreCall('empty?', [$this->localWithTag('a', 'array')])));
        self::assertSame("(%s === '')", TypedValueSpecialization::emptyCheckFragment($this->coreCall('empty?', [$this->localWithTag('s', 'string')])));
        self::assertSame('(%s === 0)', TypedValueSpecialization::emptyCheckFragment($this->coreCall('empty?', [$this->localWithTag('i', 'int')])));
        self::assertSame('(%s->count() === 0)', TypedValueSpecialization::emptyCheckFragment($this->coreCall('empty?', [$this->localWithTag('m', PersistentMapInterface::class)])));
    }

    public function test_empty_on_untyped_target_falls_back(): void
    {
        self::assertNull(TypedValueSpecialization::emptyCheckFragment($this->coreCall('empty?', [$this->local('x')])));
    }

    public function test_name_and_namespace_accessor_methods(): void
    {
        self::assertSame('getName', TypedValueSpecialization::namedAccessorMethod($this->coreCall('name', [$this->localWithTag('k', Keyword::class)])));
        self::assertSame('getNamespace', TypedValueSpecialization::namedAccessorMethod($this->coreCall('namespace', [$this->localWithTag('s', Symbol::class)])));
    }

    public function test_named_accessor_on_untagged_target_falls_back(): void
    {
        self::assertNull(TypedValueSpecialization::namedAccessorMethod($this->coreCall('name', [$this->local('x')])));
    }

    public function test_keyword_find_on_map_tagged_arg(): void
    {
        $node = new CallNode(
            $this->env(),
            new LiteralNode($this->env(), Keyword::create('k')),
            [$this->localWithTag('m', PersistentMapInterface::class)],
        );

        self::assertTrue(TypedValueSpecialization::isKeywordFind($node));
    }

    public function test_keyword_find_on_untyped_arg_falls_back(): void
    {
        $node = new CallNode(
            $this->env(),
            new LiteralNode($this->env(), Keyword::create('k')),
            [$this->local('m')],
        );

        self::assertFalse(TypedValueSpecialization::isKeywordFind($node));
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
