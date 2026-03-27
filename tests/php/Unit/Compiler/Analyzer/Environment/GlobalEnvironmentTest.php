<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GlobalEnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        Phel::clear();
    }

    public function test_set_ns(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('foo');

        $this->assertSame('foo', $env->getNs());
    }

    public function test_add_definition(): void
    {
        $env = new GlobalEnvironment();
        $meta = Phel::map();
        $env->addDefinition('foo', Symbol::create('bar'));

        $this->assertTrue($env->hasDefinition('foo', Symbol::create('bar')));
        $this->assertFalse($env->hasDefinition('bar', Symbol::create('bar')));
        $this->assertEquals($meta, $env->getDefinition('foo', Symbol::create('bar')));
        $this->assertNotInstanceOf(PersistentMapInterface::class, $env->getDefinition('bar', Symbol::create('bar')));
    }

    public function test_require_alias(): void
    {
        $env = new GlobalEnvironment();
        $env->addRequireAlias('foo', Symbol::create('b'), Symbol::create('bar'));

        $this->assertTrue($env->hasRequireAlias('foo', Symbol::create('b')));
        $this->assertFalse($env->hasRequireAlias('foo', Symbol::create('c')));
        $this->assertFalse($env->hasRequireAlias('foobar', Symbol::create('b')));
    }

    public function test_use_alias(): void
    {
        $env = new GlobalEnvironment();
        $env->addUseAlias('foo', Symbol::create('b'), Symbol::create('bar'));

        $this->assertTrue($env->hasUseAlias('foo', Symbol::create('b')));
        $this->assertFalse($env->hasUseAlias('foo', Symbol::create('c')));
        $this->assertFalse($env->hasUseAlias('foobar', Symbol::create('b')));
    }

    public function test_resolve_dir(): void
    {
        $env = new GlobalEnvironment();
        $nodeEnv = NodeEnvironment::empty();
        $sym = Symbol::create('__DIR__');
        $sym->setStartLocation(new SourceLocation(__FILE__, 0, 0));

        $this->assertEquals(
            new LiteralNode($nodeEnv, __DIR__),
            $env->resolve($sym, $nodeEnv),
        );
    }

    public function test_resolve_file(): void
    {
        $env = new GlobalEnvironment();
        $nodeEnv = NodeEnvironment::empty();
        $sym = Symbol::create('__FILE__');
        $sym->setStartLocation(new SourceLocation(__FILE__, 0, 0));

        $this->assertEquals(
            new LiteralNode($nodeEnv, __FILE__),
            $env->resolve($sym, $nodeEnv),
        );
    }

    public function test_resolve_absolute_php_class(): void
    {
        $env = new GlobalEnvironment();
        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new PhpClassNameNode(
                $nodeEnv,
                Symbol::create('\\Exception'),
            ),
            $env->resolve(Symbol::create('\\Exception'), $nodeEnv),
        );
    }

    public function test_resolve_alias(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('foo');
        $env->addUseAlias('foo', Symbol::create('b'), Symbol::create('bar'));

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new PhpClassNameNode(
                $nodeEnv,
                Symbol::create('bar'),
            ),
            $env->resolve(Symbol::create('b'), $nodeEnv),
        );
    }

    public function test_resolve_refer_definition(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('foo', Symbol::create('x'));
        $env->setNs('bar');
        $env->addRefer('bar', Symbol::create('x'), Symbol::create('foo'));

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new GlobalVarNode(
                $nodeEnv,
                'foo',
                Symbol::create('x'),
                Phel::map(),
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_private_definition_from_refer(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('foo', Symbol::create('x'));
        Phel::addDefinition(
            'foo',
            'x',
            null,
            Phel::map(Keyword::create('private'), true),
        );
        $env->setNs('bar');
        $env->addRefer('bar', Symbol::create('x'), Symbol::create('foo'));

        $nodeEnv = NodeEnvironment::empty();

        $this->assertNotInstanceOf(
            AbstractNode::class,
            $env->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_definition_in_same_ns(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('bar');
        $env->addDefinition('bar', Symbol::create('x'));

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new GlobalVarNode(
                $nodeEnv,
                'bar',
                Symbol::create('x'),
                Phel::map(),
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_interface_in_same_ns(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('bar');
        $env->addInterface('bar', Symbol::create('x'));

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new PhpClassNameNode(
                $nodeEnv,
                Symbol::createForNamespace('bar', 'x'),
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_definition_in_phel_core(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('phel\\core', Symbol::create('x'));
        $env->setNs('bar');

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new GlobalVarNode(
                $nodeEnv,
                'phel\\core',
                Symbol::create('x'),
                Phel::map(),
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_private_definition_in_phel_core(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('phel\\core', Symbol::create('x'));
        Phel::addDefinition('phel\\core', 'x', null, Phel::map(Keyword::create('private'), true));
        $env->setNs('bar');
        $nodeEnv = NodeEnvironment::empty();

        $this->assertNotInstanceOf(
            AbstractNode::class,
            $env->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_interface_in_phel_core(): void
    {
        $env = new GlobalEnvironment();
        $env->addInterface('phel\\core', Symbol::create('x'));
        $env->setNs('bar');

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new PhpClassNameNode(
                $nodeEnv,
                Symbol::createForNamespace('phel\\core', 'x'),
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_can_not_resolve_symbol_without_alias(): void
    {
        $env = new GlobalEnvironment();
        $this->assertNotInstanceOf(
            AbstractNode::class,
            $env->resolve(Symbol::create('foo'), NodeEnvironment::empty()),
        );
    }

    // ========================================

    public function test_resolve_absolute_definition_name(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('bar', Symbol::create('x'));
        $env->setNs('foo');

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new GlobalVarNode(
                $nodeEnv,
                'bar',
                Symbol::create('x'),
                Phel::map(),
            ),
            $env->resolve(Symbol::createForNamespace('bar', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_absolute_definition_from_alias(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('bar', Symbol::create('x'));
        $env->setNs('foo');
        $env->addRequireAlias('foo', Symbol::create('b'), Symbol::create('bar'));

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new GlobalVarNode(
                $nodeEnv,
                'bar',
                Symbol::create('x'),
                Phel::map(),
            ),
            $env->resolve(Symbol::createForNamespace('b', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_private_absolute_definition_name(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('bar', Symbol::create('x'));
        Phel::addDefinition('bar', 'x', null, Phel::map(Keyword::create('private'), true));
        $env->setNs('foo');
        $nodeEnv = NodeEnvironment::empty();

        $this->assertNotInstanceOf(
            AbstractNode::class,
            $env->resolve(Symbol::createForNamespace('bar', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_absolute_interface_name(): void
    {
        $env = new GlobalEnvironment();
        $env->addInterface('bar', Symbol::create('x'));
        $env->setNs('foo');

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new PhpClassNameNode(
                $nodeEnv,
                Symbol::createForNamespace('bar', 'x'),
            ),
            $env->resolve(Symbol::createForNamespace('bar', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_interface_from_alias(): void
    {
        $env = new GlobalEnvironment();
        $env->addInterface('bar', Symbol::create('x'));
        $env->setNs('foo');
        $env->addRequireAlias('foo', Symbol::create('b'), Symbol::create('bar'));

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            new PhpClassNameNode(
                $nodeEnv,
                Symbol::createForNamespace('bar', 'x'),
            ),
            $env->resolve(Symbol::createForNamespace('b', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_as_symbol(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('bar');
        $env->addDefinition('bar', Symbol::create('x'));

        $nodeEnv = NodeEnvironment::empty();

        $this->assertEquals(
            Symbol::createForNamespace('bar', 'x'),
            $env->resolveAsSymbol(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_as_symbol_not_existing(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('bar');

        $nodeEnv = NodeEnvironment::empty();

        $this->assertNotInstanceOf(
            Symbol::class,
            $env->resolveAsSymbol(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_get_refers_returns_empty_array_for_unknown_namespace(): void
    {
        $env = new GlobalEnvironment();

        $this->assertSame([], $env->getRefers('unknown'));
    }

    public function test_get_refers_returns_added_refers(): void
    {
        $env = new GlobalEnvironment();
        $env->addRefer('foo', Symbol::create('x'), Symbol::create('bar'));
        $env->addRefer('foo', Symbol::create('y'), Symbol::create('baz'));

        $refers = $env->getRefers('foo');

        $this->assertCount(2, $refers);
        $this->assertSame('bar', $refers['x']->getName());
        $this->assertSame('baz', $refers['y']->getName());
    }

    public function test_get_require_aliases_returns_empty_array_for_unknown_namespace(): void
    {
        $env = new GlobalEnvironment();

        $this->assertSame([], $env->getRequireAliases('unknown'));
    }

    public function test_get_require_aliases_returns_added_aliases(): void
    {
        $env = new GlobalEnvironment();
        $env->addRequireAlias('foo', Symbol::create('b'), Symbol::create('bar'));
        $env->addRequireAlias('foo', Symbol::create('z'), Symbol::create('baz'));

        $aliases = $env->getRequireAliases('foo');

        $this->assertCount(2, $aliases);
        $this->assertSame('bar', $aliases['b']->getName());
        $this->assertSame('baz', $aliases['z']->getName());
    }

    public function test_get_use_aliases_returns_empty_array_for_unknown_namespace(): void
    {
        $env = new GlobalEnvironment();

        $this->assertSame([], $env->getUseAliases('unknown'));
    }

    public function test_get_use_aliases_returns_added_aliases(): void
    {
        $env = new GlobalEnvironment();
        $env->addUseAlias('foo', Symbol::create('b'), Symbol::create('bar'));
        $env->addUseAlias('foo', Symbol::create('z'), Symbol::create('baz'));

        $aliases = $env->getUseAliases('foo');

        $this->assertCount(2, $aliases);
        $this->assertSame('bar', $aliases['b']->getName());
        $this->assertSame('baz', $aliases['z']->getName());
    }

    public function test_get_refers_does_not_leak_across_namespaces(): void
    {
        $env = new GlobalEnvironment();
        $env->addRefer('foo', Symbol::create('x'), Symbol::create('bar'));

        $this->assertSame([], $env->getRefers('other'));
    }

    public function test_has_definition_with_hyphenated_namespace(): void
    {
        $env = new GlobalEnvironment();
        $sym = Symbol::create('m');
        $env->addDefinition('foo-bar', $sym);
        Phel::addDefinition('foo_bar', 'm', null);

        self::assertTrue($env->hasDefinition('foo-bar', $sym));
        self::assertNull($env->getDefinition('foo-bar', Symbol::create('other')));
    }

    public function test_add_duplicate_definition_throws_exception(): void
    {
        $env = new GlobalEnvironment();
        $sym = Symbol::create('x');
        $sym->setStartLocation(new SourceLocation(__FILE__, 1, 0));

        $env->addDefinition('foo', $sym);
        Phel::addDefinition('foo', 'x', 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Symbol x is already bound in namespace foo in ' . __FILE__ . ':1');

        $env->addDefinition('foo', $sym);
    }

    public function test_snapshot_captures_current_state(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('test-ns');
        $env->addDefinition('test-ns', Symbol::create('x'));
        $env->addRefer('test-ns', Symbol::create('y'), Symbol::create('other'));
        $env->addRequireAlias('test-ns', Symbol::create('a'), Symbol::create('alias-ns'));
        $env->addUseAlias('test-ns', Symbol::create('MyClass'), Symbol::create('\\Full\\MyClass'));
        $env->addInterface('test-ns', Symbol::create('IFoo'));

        $snapshot = $env->snapshot();

        $this->assertSame('test-ns', $snapshot['ns']);
        $this->assertArrayHasKey('test-ns', $snapshot['definitions']);
        $this->assertTrue($snapshot['definitions']['test-ns']['x']);
        $this->assertArrayHasKey('test-ns', $snapshot['refers']);
        $this->assertSame('other', $snapshot['refers']['test-ns']['y']->getName());
        $this->assertArrayHasKey('test-ns', $snapshot['requireAliases']);
        $this->assertSame('alias-ns', $snapshot['requireAliases']['test-ns']['a']->getName());
        $this->assertArrayHasKey('test-ns', $snapshot['useAliases']);
        $this->assertSame('\\Full\\MyClass', $snapshot['useAliases']['test-ns']['MyClass']->getName());
        $this->assertArrayHasKey('test-ns', $snapshot['interfaces']);
        $this->assertSame('IFoo', $snapshot['interfaces']['test-ns']['IFoo']->getName());
    }

    public function test_restore_rolls_back_namespace(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('original');

        $snapshot = $env->snapshot();

        $env->setNs('changed');
        $this->assertSame('changed', $env->getNs());

        $env->restore($snapshot);
        $this->assertSame('original', $env->getNs());
    }

    public function test_restore_rolls_back_added_definitions(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('test-ns');

        $snapshot = $env->snapshot();

        $env->addDefinition('test-ns', Symbol::create('new-def'));
        $this->assertTrue($env->hasDefinition('test-ns', Symbol::create('new-def')));

        $env->restore($snapshot);
        $this->assertFalse($env->hasDefinition('test-ns', Symbol::create('new-def')));
    }

    public function test_restore_rolls_back_added_require_alias(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('test-ns');

        $snapshot = $env->snapshot();

        $env->addRequireAlias('test-ns', Symbol::create('a'), Symbol::create('alias-ns'));
        $this->assertTrue($env->hasRequireAlias('test-ns', Symbol::create('a')));

        $env->restore($snapshot);
        $this->assertFalse($env->hasRequireAlias('test-ns', Symbol::create('a')));
    }

    public function test_restore_rolls_back_added_use_alias(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('test-ns');

        $snapshot = $env->snapshot();

        $env->addUseAlias('test-ns', Symbol::create('MyClass'), Symbol::create('\\Full\\MyClass'));
        $this->assertTrue($env->hasUseAlias('test-ns', Symbol::create('MyClass')));

        $env->restore($snapshot);
        $this->assertFalse($env->hasUseAlias('test-ns', Symbol::create('MyClass')));
    }

    public function test_restore_rolls_back_added_refers(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('test-ns');

        $snapshot = $env->snapshot();

        $env->addRefer('test-ns', Symbol::create('y'), Symbol::create('other'));
        $this->assertCount(1, $env->getRefers('test-ns'));

        $env->restore($snapshot);
        $this->assertSame([], $env->getRefers('test-ns'));
    }

    public function test_restore_preserves_pre_existing_state(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('test-ns');
        $env->addDefinition('test-ns', Symbol::create('existing'));
        $env->addRequireAlias('test-ns', Symbol::create('b'), Symbol::create('bar'));

        $snapshot = $env->snapshot();

        $env->addDefinition('test-ns', Symbol::create('new-def'));
        $env->addRequireAlias('test-ns', Symbol::create('c'), Symbol::create('baz'));

        $env->restore($snapshot);

        $this->assertTrue($env->hasDefinition('test-ns', Symbol::create('existing')));
        $this->assertFalse($env->hasDefinition('test-ns', Symbol::create('new-def')));
        $this->assertTrue($env->hasRequireAlias('test-ns', Symbol::create('b')));
        $this->assertFalse($env->hasRequireAlias('test-ns', Symbol::create('c')));
    }
}
