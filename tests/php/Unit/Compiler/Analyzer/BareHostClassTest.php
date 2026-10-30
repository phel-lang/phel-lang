<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Application\Analyzer;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\BareHostClass;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

/**
 * The class-position half of #3064: value position reads a bare all-caps name
 * as the constant, and a member target, constructor or callable re-reads that
 * same node as a class. Explicit class imports can also be recovered when a
 * namespace var shadows their value, while lexical locals stay dynamic.
 */
final class BareHostClassTest extends TestCase
{
    public function test_class_positions_recover_an_import_shadowed_by_an_own_var(): void
    {
        $global = new GlobalEnvironment();
        $global->setNs('app');
        $global->addDefinition('app', Symbol::create('Date'));
        $global->addUseAlias('app', Symbol::create('Date'), Symbol::create('\\DateTime'));

        $analyzer = new Analyzer($global);
        $env = NodeEnvironment::empty();
        $form = Symbol::create('Date');

        self::assertEquals(
            new PhpClassNameNode($env, Symbol::create('\\DateTime')),
            BareHostClass::reread($form, $analyzer->resolve($form, $env), $env, $analyzer),
        );
    }

    public function test_a_lexical_binding_blocks_import_recovery(): void
    {
        $global = new GlobalEnvironment();
        $global->setNs('app');

        $form = Symbol::create('Date');
        $global->addDefinition('app', $form);
        $global->addUseAlias('app', $form, Symbol::create('\\DateTime'));

        $analyzer = new Analyzer($global);
        $env = NodeEnvironment::empty()->withLocalAndShadow($form, $form);

        self::assertNull(BareHostClass::reread($form, $analyzer->resolve($form, $env), $env, $analyzer));
    }

    public function test_a_bare_all_caps_constant_fallback_becomes_a_class(): void
    {
        $env = NodeEnvironment::empty();
        $form = Symbol::create('WP_CLI');

        $node = BareHostClass::reread($form, new PhpVarNode($env, 'WP_CLI'), $env);

        self::assertInstanceOf(PhpClassNameNode::class, $node);
        self::assertSame('\WP_CLI', $node->getName()->getName());
    }

    public function test_an_underscore_prefixed_name_is_still_all_caps(): void
    {
        $env = NodeEnvironment::empty();

        self::assertInstanceOf(
            PhpClassNameNode::class,
            BareHostClass::reread(Symbol::create('_WP_CLI2'), new PhpVarNode($env, '_WP_CLI2'), $env),
        );
    }

    public function test_a_mixed_case_name_is_left_alone(): void
    {
        // It never reached the constant fallback: the resolver reads it as a
        // class already, so there is nothing to re-read.
        $env = NodeEnvironment::empty();

        self::assertNull(
            BareHostClass::reread(Symbol::create('DateTime'), new PhpVarNode($env, 'DateTime'), $env),
        );
    }

    public function test_a_qualified_symbol_is_left_alone(): void
    {
        // `php/NAME` is the explicit constant, in every position.
        $env = NodeEnvironment::empty();
        $form = Symbol::createForNamespace('php', 'WP_CLI');

        self::assertNull(BareHostClass::reread($form, new PhpVarNode($env, 'WP_CLI'), $env));
    }

    public function test_a_local_is_left_alone(): void
    {
        $env = NodeEnvironment::empty();
        $form = Symbol::create('WP_CLI');

        self::assertNull(BareHostClass::reread($form, new LocalVarNode($env, $form), $env));
    }

    public function test_a_node_naming_something_else_is_left_alone(): void
    {
        // A `php/OTHER` target resolves to a PhpVarNode with a different name,
        // so the pair does not identify the fallback.
        $env = NodeEnvironment::empty();

        self::assertNull(
            BareHostClass::reread(Symbol::create('WP_CLI'), new PhpVarNode($env, 'OTHER'), $env),
        );
    }

    public function test_an_unresolved_form_is_left_alone(): void
    {
        $env = NodeEnvironment::empty();

        self::assertNull(BareHostClass::reread(Symbol::create('WP_CLI'), null, $env));
        self::assertNull(BareHostClass::reread('WP_CLI', null, $env));
    }
}
