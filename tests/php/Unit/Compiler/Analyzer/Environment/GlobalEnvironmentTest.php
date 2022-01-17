<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use PHPUnit\Framework\TestCase;

final class GlobalEnvironmentTest extends TestCase
{
    public function setUp(): void
    {
        Registry::getInstance()->clear();
    }

    public function test_set_ns(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('foo');

        $this->assertEquals('foo', $env->getNs());
    }

    public function test_add_definition(): void
    {
        $env = new GlobalEnvironment();
        $meta = TypeFactory::getInstance()->emptyPersistentMap();
        $env->addDefinition('foo', Symbol::create('bar'));

        $this->assertTrue($env->hasDefinition('foo', Symbol::create('bar')));
        $this->assertFalse($env->hasDefinition('bar', Symbol::create('bar')));
        $this->assertEquals($meta, $env->getDefinition('foo', Symbol::create('bar')));
        $this->assertNull($env->getDefinition('bar', Symbol::create('bar')));
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
            $env->resolve($sym, $nodeEnv)
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
            $env->resolve($sym, $nodeEnv)
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
                null
            ),
            $env->resolve(Symbol::create('\\Exception'), $nodeEnv)
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
                null
            ),
            $env->resolve(Symbol::create('b'), $nodeEnv)
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
                TypeFactory::getInstance()->emptyPersistentMap()
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv)
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
                TypeFactory::getInstance()->emptyPersistentMap()
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv)
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
                Symbol::createForNamespace('bar', 'x')
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv)
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
                TypeFactory::getInstance()->emptyPersistentMap()
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv)
        );
    }

    public function test_resolve_private_definition_in_phel_core(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('phel\\core', Symbol::create('x'));
        Registry::getInstance()->addDefinition('phel\\core', 'x', null, TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('private'), true));
        $env->setNs('bar');
        $nodeEnv = NodeEnvironment::empty();

        $this->assertNull(
            $env->resolve(Symbol::create('x'), $nodeEnv)
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
                Symbol::createForNamespace('phel\\core', 'x')
            ),
            $env->resolve(Symbol::create('x'), $nodeEnv)
        );
    }

    public function test_can_not_resolve_symbol_without_alias(): void
    {
        $env = new GlobalEnvironment();
        $this->assertNull(
            $env->resolve(Symbol::create('foo'), NodeEnvironment::empty())
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
                TypeFactory::getInstance()->emptyPersistentMap()
            ),
            $env->resolve(Symbol::createForNamespace('bar', 'x'), $nodeEnv)
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
                TypeFactory::getInstance()->emptyPersistentMap()
            ),
            $env->resolve(Symbol::createForNamespace('b', 'x'), $nodeEnv)
        );
    }

    public function test_resolve_private_absolute_definition_name(): void
    {
        $env = new GlobalEnvironment();
        $env->addDefinition('bar', Symbol::create('x'));
        Registry::getInstance()->addDefinition('bar', 'x', null, TypeFactory::getInstance()->persistentMapFromKVs(Keyword::create('private'), true));
        $env->setNs('foo');
        $nodeEnv = NodeEnvironment::empty();

        $this->assertNull(
            $env->resolve(Symbol::createForNamespace('bar', 'x'), $nodeEnv)
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
                Symbol::createForNamespace('bar', 'x')
            ),
            $env->resolve(Symbol::createForNamespace('bar', 'x'), $nodeEnv)
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
                Symbol::createForNamespace('bar', 'x')
            ),
            $env->resolve(Symbol::createForNamespace('b', 'x'), $nodeEnv)
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
            $env->resolveAsSymbol(Symbol::create('x'), $nodeEnv)
        );
    }

    public function test_resolve_as_symbol_not_existing(): void
    {
        $env = new GlobalEnvironment();
        $env->setNs('bar');
        $nodeEnv = NodeEnvironment::empty();

        $this->assertNull(
            $env->resolveAsSymbol(Symbol::create('x'), $nodeEnv)
        );
    }
}
