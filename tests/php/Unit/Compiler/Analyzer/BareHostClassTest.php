<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer;

use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\BareHostClass;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

/**
 * The class-position half of #3064: value position reads a bare all-caps name
 * as the constant, and a member target, constructor or callable re-reads that
 * same node as a class. What has to hold is that only the constant fallback is
 * re-read, since everything else at that position is a genuine dynamic target.
 */
final class BareHostClassTest extends TestCase
{
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
