<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpObjectCallDispatch;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpObjectCallDispatchResolver;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class PhpObjectCallDispatchResolverTest extends TestCase
{
    private PhpObjectCallDispatchResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PhpObjectCallDispatchResolver();
    }

    public function test_static_form_dispatches_statically(): void
    {
        $node = $this->methodCallOn($this->untypedLocal(), isStatic: true);

        self::assertSame(PhpObjectCallDispatch::StaticClass, $this->resolver->resolve($node));
    }

    /**
     * `Foo->m()` is not valid PHP, so a class-name target is static even when the
     * call was written as `php/->`.
     */
    public function test_class_name_target_dispatches_statically_even_when_written_as_instance_call(): void
    {
        $target = new PhpClassNameNode(
            NodeEnvironment::empty(),
            Symbol::create('\\DateTimeImmutable'),
        );

        $node = $this->methodCallOn($target, isStatic: false);

        self::assertSame(PhpObjectCallDispatch::StaticClass, $this->resolver->resolve($node));
    }

    public function test_string_tagged_receiver_dispatches_statically(): void
    {
        $node = $this->methodCallOn($this->localTagged('string'), isStatic: false);

        self::assertSame(PhpObjectCallDispatch::StaticClass, $this->resolver->resolve($node));
    }

    public function test_string_literal_receiver_dispatches_statically(): void
    {
        $target = new LiteralNode(NodeEnvironment::empty(), '\\DateTimeImmutable');

        $node = $this->methodCallOn($target, isStatic: false);

        self::assertSame(PhpObjectCallDispatch::StaticClass, $this->resolver->resolve($node));
    }

    /**
     * A leading backslash must not defeat the string check, since `TagNormalizer`
     * is what reconciles `string` with `\string`.
     */
    public function test_backslash_prefixed_string_tag_dispatches_statically(): void
    {
        $node = $this->methodCallOn($this->localTagged('\\string'), isStatic: false);

        self::assertSame(PhpObjectCallDispatch::StaticClass, $this->resolver->resolve($node));
    }

    public function test_constructor_result_dispatches_as_instance(): void
    {
        $target = new PhpNewNode(
            NodeEnvironment::empty(),
            new PhpClassNameNode(NodeEnvironment::empty(), Symbol::create('\\DateTimeImmutable')),
            [],
        );

        $node = $this->methodCallOn($target, isStatic: false);

        self::assertSame(PhpObjectCallDispatch::Instance, $this->resolver->resolve($node));
    }

    public function test_chained_call_dispatches_as_instance(): void
    {
        $inner = $this->methodCallOn($this->untypedLocal(), isStatic: false);

        $node = $this->methodCallOn($inner, isStatic: false);

        self::assertSame(PhpObjectCallDispatch::Instance, $this->resolver->resolve($node));
    }

    /**
     * A non-string tag proves the receiver is not a class name, so no runtime
     * test is emitted even though the call will fail either way.
     */
    public function test_non_string_tagged_receiver_dispatches_as_instance(): void
    {
        $node = $this->methodCallOn($this->localTagged('int'), isStatic: false);

        self::assertSame(PhpObjectCallDispatch::Instance, $this->resolver->resolve($node));
    }

    public function test_untyped_receiver_defers_to_runtime(): void
    {
        $node = $this->methodCallOn($this->untypedLocal(), isStatic: false);

        self::assertSame(PhpObjectCallDispatch::Runtime, $this->resolver->resolve($node));
    }

    /**
     * `$t::x` reads a class constant and `$t->x` a property, so a property access
     * on an untyped receiver keeps `->` rather than choosing between two
     * different members at runtime.
     */
    public function test_property_access_on_untyped_receiver_stays_an_instance_access(): void
    {
        $node = new PhpObjectCallNode(
            NodeEnvironment::empty(),
            $this->untypedLocal(),
            new PropertyOrConstantAccessNode(NodeEnvironment::empty(), Symbol::create('prop')),
            isStatic: false,
            isMethodCall: false,
        );

        self::assertSame(PhpObjectCallDispatch::Instance, $this->resolver->resolve($node));
    }

    private function methodCallOn(AbstractNode $target, bool $isStatic): PhpObjectCallNode
    {
        return new PhpObjectCallNode(
            NodeEnvironment::empty(),
            $target,
            new MethodCallNode(NodeEnvironment::empty(), Symbol::create('m'), []),
            isStatic: $isStatic,
            isMethodCall: true,
        );
    }

    private function untypedLocal(): LocalVarNode
    {
        return new LocalVarNode(NodeEnvironment::empty(), Symbol::create('x'));
    }

    private function localTagged(string $tag): LocalVarNode
    {
        $symbol = Symbol::create('x')->withMeta(
            TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('tag'), $tag),
        );

        return new LocalVarNode(NodeEnvironment::empty(), $symbol);
    }
}
