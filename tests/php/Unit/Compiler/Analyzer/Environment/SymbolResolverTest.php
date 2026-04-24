<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\MagicConstantResolver;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\SymbolResolver;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class SymbolResolverTest extends TestCase
{
    private GlobalEnvironment $globalEnv;

    private SymbolResolver $resolver;

    protected function setUp(): void
    {
        Phel::clear();
        $this->globalEnv = new GlobalEnvironment();
        $this->resolver = new SymbolResolver($this->globalEnv, new MagicConstantResolver());
    }

    public function test_resolve_magic_dir_constant(): void
    {
        $nodeEnv = NodeEnvironment::empty();
        $sym = Symbol::create('__DIR__');
        $sym->setStartLocation(new SourceLocation(__FILE__, 0, 0));

        self::assertEquals(
            new LiteralNode($nodeEnv, __DIR__),
            $this->resolver->resolve($sym, $nodeEnv),
        );
    }

    public function test_resolve_magic_file_constant(): void
    {
        $nodeEnv = NodeEnvironment::empty();
        $sym = Symbol::create('__FILE__');
        $sym->setStartLocation(new SourceLocation(__FILE__, 0, 0));

        self::assertEquals(
            new LiteralNode($nodeEnv, __FILE__),
            $this->resolver->resolve($sym, $nodeEnv),
        );
    }

    public function test_resolve_absolute_php_class(): void
    {
        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new PhpClassNameNode($nodeEnv, Symbol::create('\\Exception')),
            $this->resolver->resolve(Symbol::create('\\Exception'), $nodeEnv),
        );
    }

    public function test_resolve_via_use_alias(): void
    {
        $this->globalEnv->setNs('foo');
        $this->globalEnv->addUseAlias('foo', Symbol::create('b'), Symbol::create('bar'));

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new PhpClassNameNode($nodeEnv, Symbol::create('bar')),
            $this->resolver->resolve(Symbol::create('b'), $nodeEnv),
        );
    }

    public function test_resolve_qualified_symbol_via_require_alias(): void
    {
        $this->globalEnv->addDefinition('bar', Symbol::create('x'));
        $this->globalEnv->setNs('foo');
        $this->globalEnv->addRequireAlias('foo', Symbol::create('b'), Symbol::create('bar'));

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'bar', Symbol::create('x'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('b', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_qualified_symbol_without_alias_uses_namespace_as_is(): void
    {
        $this->globalEnv->addDefinition('bar', Symbol::create('x'));
        $this->globalEnv->setNs('foo');

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'bar', Symbol::create('x'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('bar', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_unqualified_via_refer(): void
    {
        $this->globalEnv->addDefinition('foo', Symbol::create('x'));
        $this->globalEnv->setNs('bar');
        $this->globalEnv->addRefer('bar', Symbol::create('x'), Symbol::create('foo'));

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'foo', Symbol::create('x'), Phel::map()),
            $this->resolver->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_unqualified_falls_back_to_current_ns(): void
    {
        $this->globalEnv->setNs('bar');
        $this->globalEnv->addDefinition('bar', Symbol::create('x'));

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'bar', Symbol::create('x'), Phel::map()),
            $this->resolver->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_unqualified_falls_back_to_phel_core(): void
    {
        $this->globalEnv->addDefinition('phel\\core', Symbol::create('x'));
        $this->globalEnv->setNs('bar');

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'phel\\core', Symbol::create('x'), Phel::map()),
            $this->resolver->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_returns_null_when_symbol_is_unknown(): void
    {
        self::assertNotInstanceOf(
            AbstractNode::class,
            $this->resolver->resolve(Symbol::create('foo'), NodeEnvironment::empty()),
        );
    }

    public function test_resolve_ignores_private_definition_by_default(): void
    {
        $this->globalEnv->addDefinition('bar', Symbol::create('x'));
        Phel::addDefinition('bar', 'x', null, Phel::map(Keyword::create('private'), true));
        $this->globalEnv->setNs('foo');

        self::assertNotInstanceOf(
            AbstractNode::class,
            $this->resolver->resolve(Symbol::createForNamespace('bar', 'x'), NodeEnvironment::empty()),
        );
    }

    public function test_resolve_allows_private_definition_when_access_level_active(): void
    {
        $this->globalEnv->addDefinition('bar', Symbol::create('x'));
        Phel::addDefinition('bar', 'x', null, Phel::map(Keyword::create('private'), true));
        $this->globalEnv->setNs('foo');
        $this->globalEnv->addLevelToAllowPrivateAccess();

        $nodeEnv = NodeEnvironment::empty();

        self::assertInstanceOf(
            GlobalVarNode::class,
            $this->resolver->resolve(Symbol::createForNamespace('bar', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_current_ns_exposes_own_private_definitions(): void
    {
        $this->globalEnv->setNs('bar');
        $this->globalEnv->addDefinition('bar', Symbol::create('x'));
        Phel::addDefinition('bar', 'x', null, Phel::map(Keyword::create('private'), true));

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode(
                $nodeEnv,
                'bar',
                Symbol::create('x'),
                Phel::map(Keyword::create('private'), true),
            ),
            $this->resolver->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_interface_in_current_ns(): void
    {
        $this->globalEnv->setNs('bar');
        $this->globalEnv->addInterface('bar', Symbol::create('x'));

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new PhpClassNameNode($nodeEnv, Symbol::createForNamespace('bar', 'x')),
            $this->resolver->resolve(Symbol::create('x'), $nodeEnv),
        );
    }

    public function test_resolve_interface_via_alias(): void
    {
        $this->globalEnv->addInterface('bar', Symbol::create('x'));
        $this->globalEnv->setNs('foo');
        $this->globalEnv->addRequireAlias('foo', Symbol::create('b'), Symbol::create('bar'));

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new PhpClassNameNode($nodeEnv, Symbol::createForNamespace('bar', 'x')),
            $this->resolver->resolve(Symbol::createForNamespace('b', 'x'), $nodeEnv),
        );
    }

    public function test_resolve_fqn_with_dot_namespace_separator(): void
    {
        $this->globalEnv->addDefinition('phel\\stacktrace', Symbol::create('print-cause-trace'));
        $this->globalEnv->setNs('app\\core');

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'phel\\stacktrace', Symbol::create('print-cause-trace'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('phel.stacktrace', 'print-cause-trace'), $nodeEnv),
        );
    }

    public function test_resolve_dot_namespace_via_require_alias(): void
    {
        $this->globalEnv->addDefinition('phel\\stacktrace', Symbol::create('print-cause-trace'));
        $this->globalEnv->setNs('app\\core');
        $this->globalEnv->addRequireAlias('app\\core', Symbol::create('phel\\stacktrace'), Symbol::create('phel\\stacktrace'));

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'phel\\stacktrace', Symbol::create('print-cause-trace'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('phel.stacktrace', 'print-cause-trace'), $nodeEnv),
        );
    }

    public function test_resolve_clojure_backslash_fqn_remaps_to_phel(): void
    {
        Registry::getInstance()->addDefinition('phel\\stacktrace', '__ns_marker', true);
        $this->globalEnv->addDefinition('phel\\stacktrace', Symbol::create('print-cause-trace'));
        $this->globalEnv->setNs('app\\core');

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'phel\\stacktrace', Symbol::create('print-cause-trace'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('clojure\\stacktrace', 'print-cause-trace'), $nodeEnv),
        );
    }

    public function test_resolve_clojure_dot_fqn_normalizes_and_remaps(): void
    {
        Registry::getInstance()->addDefinition('phel\\stacktrace', '__ns_marker', true);
        $this->globalEnv->addDefinition('phel\\stacktrace', Symbol::create('print-cause-trace'));
        $this->globalEnv->setNs('app\\core');

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'phel\\stacktrace', Symbol::create('print-cause-trace'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('clojure.stacktrace', 'print-cause-trace'), $nodeEnv),
        );
    }

    public function test_resolve_clojure_fqn_not_remapped_when_phel_missing(): void
    {
        $this->globalEnv->addDefinition('clojure\\custom-lib', Symbol::create('my-fn'));
        $this->globalEnv->setNs('app\\core');

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'clojure\\custom-lib', Symbol::create('my-fn'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('clojure\\custom-lib', 'my-fn'), $nodeEnv),
        );
    }

    public function test_resolve_backslash_fqn_still_works(): void
    {
        $this->globalEnv->addDefinition('phel\\stacktrace', Symbol::create('print-cause-trace'));
        $this->globalEnv->setNs('app\\core');

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'phel\\stacktrace', Symbol::create('print-cause-trace'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('phel\\stacktrace', 'print-cause-trace'), $nodeEnv),
        );
    }

    public function test_resolve_bare_dot_fqn_as_php_class(): void
    {
        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new PhpClassNameNode($nodeEnv, Symbol::create('\\' . Symbol::class)),
            $this->resolver->resolve(Symbol::create('Phel.Lang.Symbol'), $nodeEnv),
        );
    }

    public function test_resolve_bare_dot_fqn_falls_through_when_lowercase(): void
    {
        $nodeEnv = NodeEnvironment::empty();

        self::assertNotInstanceOf(
            PhpClassNameNode::class,
            $this->resolver->resolve(Symbol::create('phel.foo'), $nodeEnv),
        );
    }

    public function test_resolve_bare_name_without_dot_is_not_class_fqn(): void
    {
        $nodeEnv = NodeEnvironment::empty();

        self::assertNotInstanceOf(
            PhpClassNameNode::class,
            $this->resolver->resolve(Symbol::create('Foo'), $nodeEnv),
        );
    }

    public function test_resolve_clojure_fqn_uses_munged_registry_lookup(): void
    {
        Registry::getInstance()->addDefinition('phel\\my_lib', '__ns_marker', true);
        $this->globalEnv->addDefinition('phel\\my-lib', Symbol::create('some-fn'));
        $this->globalEnv->setNs('app\\core');

        $nodeEnv = NodeEnvironment::empty();

        self::assertEquals(
            new GlobalVarNode($nodeEnv, 'phel\\my-lib', Symbol::create('some-fn'), Phel::map()),
            $this->resolver->resolve(Symbol::createForNamespace('clojure.my-lib', 'some-fn'), $nodeEnv),
        );
    }
}
