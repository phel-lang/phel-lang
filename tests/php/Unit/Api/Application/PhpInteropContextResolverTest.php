<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpInteropContextResolver;
use Phel\Api\Transfer\PhpInteropContext;
use PhelTest\Support\Fixtures\PhpInterop\ChainFixture;
use PHPUnit\Framework\TestCase;

use function strlen;

final class PhpInteropContextResolverTest extends TestCase
{
    private PhpInteropContextResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PhpInteropContextResolver();
    }

    public function test_instance_member_after_reader_tag_binding(): void
    {
        $source = "(let [^\\DateTimeImmutable dt (make)]\n  (php/-> dt (get";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('get', $context->prefix);
    }

    public function test_instance_member_with_tag_map_binding(): void
    {
        $source = "(let [^{:tag \\DateTimeImmutable} dt (make)]\n  (php/-> dt get";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('get', $context->prefix);
    }

    public function test_instance_member_from_inline_php_new_receiver(): void
    {
        $source = '(php/-> (php/new \\DateTimeImmutable) get';
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
    }

    public function test_instance_member_from_php_new_let_binding(): void
    {
        $source = "(let [dt (php/new \\DateTimeImmutable)]\n  (php/-> dt get";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
    }

    public function test_static_member_after_class_literal(): void
    {
        $source = '(php/:: \\DateTimeImmutable create';
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('create', $context->prefix);
    }

    public function test_class_name_after_php_new(): void
    {
        $context = $this->resolveAtEnd('(php/new \\DateTimeImm');

        self::assertSame(PhpInteropContext::KIND_CLASS_NAME, $context->kind);
        self::assertSame('DateTimeImm', $context->prefix);
    }

    public function test_class_name_for_fully_qualified_position(): void
    {
        $context = $this->resolveAtEnd('(def x \\DateTimeImm');

        self::assertSame(PhpInteropContext::KIND_CLASS_NAME, $context->kind);
        self::assertSame('DateTimeImm', $context->prefix);
    }

    public function test_global_function_after_php_prefix(): void
    {
        $context = $this->resolveAtEnd('(php/strle');

        self::assertSame(PhpInteropContext::KIND_GLOBAL_FUNCTION, $context->kind);
        self::assertSame('strle', $context->prefix);
    }

    public function test_global_variable_after_php_prefix(): void
    {
        $context = $this->resolveAtEnd('(php/$_S');

        self::assertSame(PhpInteropContext::KIND_GLOBAL_VARIABLE, $context->kind);
        self::assertSame('$_S', $context->prefix);
    }

    public function test_bare_sigil_is_a_global_variable_position(): void
    {
        $context = $this->resolveAtEnd('(php/$');

        self::assertSame(PhpInteropContext::KIND_GLOBAL_VARIABLE, $context->kind);
        self::assertSame('$', $context->prefix);
    }

    public function test_global_variable_outside_a_form(): void
    {
        // Not every interop position opens with a paren: `php/$_S` can be typed
        // at the head of a line, as an argument, or inside a literal.
        self::assertSame(PhpInteropContext::KIND_GLOBAL_VARIABLE, $this->resolveAtEnd('php/$_S')->kind);
        self::assertSame(PhpInteropContext::KIND_GLOBAL_VARIABLE, $this->resolveAtEnd('(get php/$_S')->kind);
        self::assertSame(PhpInteropContext::KIND_GLOBAL_VARIABLE, $this->resolveAtEnd('[php/$_S')->kind);
    }

    public function test_global_variable_is_not_resolved_in_a_string_or_comment(): void
    {
        self::assertTrue($this->resolveAtEnd('(def x "php/$_S')->isNone());
        self::assertTrue($this->resolveAtEnd('; php/$_S')->isNone());
    }

    public function test_global_variable_branch_leaves_the_other_php_positions_alone(): void
    {
        // The `php/$` branch runs between the class-name and global-function
        // branches, so pin the neighbours it could have stolen.
        self::assertSame(PhpInteropContext::KIND_GLOBAL_FUNCTION, $this->resolveAtEnd('(php/str_rep')->kind);
        self::assertSame(PhpInteropContext::KIND_CLASS_NAME, $this->resolveAtEnd('(php/new \\DateTimeImm')->kind);
        self::assertSame(PhpInteropContext::KIND_CLASS_NAME, $this->resolveAtEnd('(def x \\Countab')->kind);
    }

    public function test_static_property_on_a_class_is_not_a_superglobal(): void
    {
        // `Class/$prop` and `php/$VAR` share the `$` sigil; only the latter is
        // a superglobal position.
        $context = $this->resolveAtEnd('(\\PhelTest\\Support\\Fixtures\\PhpInterop\\StaticPropertyTarget/$reg');

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
    }

    public function test_interop_special_form_is_not_a_global_function(): void
    {
        // php/aset, php/oset, ... are special forms, not callable functions.
        self::assertTrue($this->resolveAtEnd('(php/aset')->isNone());
        self::assertTrue($this->resolveAtEnd('(php/oset')->isNone());
        self::assertTrue($this->resolveAtEnd('(php/aget')->isNone());
    }

    public function test_static_member_via_use_alias(): void
    {
        $source = "(ns app (:use Some\\Long\\Widget))\n(php/:: Widget create";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
        self::assertSame('Some\\Long\\Widget', $context->class);
        self::assertSame('create', $context->prefix);
    }

    public function test_static_member_via_use_alias_with_as(): void
    {
        $source = "(ns app (:use Some\\Long\\Widget :as W))\n(php/:: W create";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
        self::assertSame('Some\\Long\\Widget', $context->class);
    }

    public function test_static_member_via_top_level_use(): void
    {
        $source = "(use Some\\Long\\Widget)\n(php/:: Widget create";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
        self::assertSame('Some\\Long\\Widget', $context->class);
    }

    public function test_instance_member_via_use_alias_php_new_binding(): void
    {
        $source = "(ns app (:use Some\\Long\\Widget))\n(let [w (php/new Widget)]\n  (php/-> w handle";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('Some\\Long\\Widget', $context->class);
        self::assertSame('handle', $context->prefix);
    }

    public function test_instance_member_via_use_alias_reader_tag(): void
    {
        $source = "(ns app (:use Some\\Long\\Widget :as W))\n(let [^W w (x)]\n  (php/-> w handle";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('Some\\Long\\Widget', $context->class);
    }

    public function test_instance_member_with_multiline_form(): void
    {
        $source = "(php/-> (php/new \\DateTimeImmutable)\n  get";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('get', $context->prefix);
    }

    public function test_earlier_closed_form_does_not_hijack_completion(): void
    {
        $source = "(php/-> (php/new \\DateTimeImmutable) (getTimestamp))\n(php/strle";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_GLOBAL_FUNCTION, $context->kind);
        self::assertSame('strle', $context->prefix);
    }

    public function test_instance_member_through_chained_method_hop(): void
    {
        $source = '(let [^\\' . ChainFixture::class . " c (x)]\n  (php/-> c (withName \"a\") nex";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame(ChainFixture::class, $context->class);
        self::assertSame('nex', $context->prefix);
    }

    public function test_instance_member_through_inline_multi_hop_chain(): void
    {
        $source = '(php/-> (php/new \\' . ChainFixture::class . ') (withName "a") (next) siz';
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame(ChainFixture::class, $context->class);
        self::assertSame('siz', $context->prefix);
    }

    public function test_instance_member_through_union_returning_hop(): void
    {
        // `orInt` returns `self|int`; the class member advances the chain.
        $source = '(php/-> (php/new \\' . ChainFixture::class . ') (orInt) siz';
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame(ChainFixture::class, $context->class);
    }

    public function test_factory_static_return_binding_resolves_receiver(): void
    {
        $source = '(let [x (php/:: \\' . ChainFixture::class . " make)]\n  (php/-> x siz";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame(ChainFixture::class, $context->class);
    }

    public function test_indirect_binding_follows_alias(): void
    {
        $source = '(let [a (php/new \\' . ChainFixture::class . ") b a]\n  (php/-> b siz";
        $context = $this->resolveAtEnd($source);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame(ChainFixture::class, $context->class);
    }

    public function test_chain_hop_with_scalar_return_is_none(): void
    {
        // `size` returns int, so the following hop has no class to resolve.
        $source = '(php/-> (php/new \\' . ChainFixture::class . ') (size) foo';
        $context = $this->resolveAtEnd($source);

        self::assertTrue($context->isNone());
    }

    public function test_cyclic_indirect_binding_does_not_loop(): void
    {
        $source = "(let [a b b a]\n  (php/-> a foo";
        $context = $this->resolveAtEnd($source);

        self::assertTrue($context->isNone());
    }

    public function test_unknown_receiver_type_is_none(): void
    {
        $source = '(php/-> mystery-thing get';
        $context = $this->resolveAtEnd($source);

        self::assertTrue($context->isNone());
    }

    public function test_plain_phel_code_is_none(): void
    {
        $context = $this->resolveAtEnd('(defn foo [x] (inc ');

        self::assertTrue($context->isNone());
    }

    public function test_class_literal_inside_string_is_none(): void
    {
        // The `\DateTime...` lives inside a string literal, not an interop slot.
        $context = $this->resolveAtEnd('(println "path \\DateTimeImm');

        self::assertTrue($context->isNone());
    }

    public function test_class_literal_inside_line_comment_is_none(): void
    {
        $context = $this->resolveAtEnd('; see \\DateTimeImm');

        self::assertTrue($context->isNone());
    }

    public function test_interop_form_inside_string_is_none(): void
    {
        $context = $this->resolveAtEnd('(str "x" "(php/-> obj ge');

        self::assertTrue($context->isNone());
    }

    public function test_escaped_quote_does_not_reopen_interop_inside_string(): void
    {
        // The \" is an escaped quote: the string stays open, so the trailing
        // \DateTimeImm is still inside it and must not resolve to a class.
        $context = $this->resolveAtEnd('(println "a\\"b \\DateTimeImm');

        self::assertTrue($context->isNone());
    }

    public function test_completed_string_argument_does_not_suppress_interop(): void
    {
        $context = $this->resolveAtEnd('(php/-> (php/new \\DateTimeImmutable "now") ge');

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
    }

    public function test_qualified_member_is_a_static_member_position(): void
    {
        $context = $this->resolveAtEnd('(\\DateTimeImmutable/create');

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('create', $context->prefix);
    }

    public function test_qualified_member_in_value_position(): void
    {
        $context = $this->resolveAtEnd('(map \\DateTimeImmutable/ATO');

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('ATO', $context->prefix);
    }

    public function test_qualified_member_keeps_the_static_property_sigil(): void
    {
        $context = $this->resolveAtEnd('\\DateTimeImmutable/$sl');

        self::assertSame(PhpInteropContext::KIND_STATIC_MEMBER, $context->kind);
        self::assertSame('$sl', $context->prefix);
    }

    public function test_a_phel_namespace_is_not_a_qualified_member(): void
    {
        $context = $this->resolveAtEnd('(my-ns/some-fn');

        self::assertTrue($context->isNone());
    }

    public function test_dot_method_resolves_the_receiver_after_the_cursor(): void
    {
        $source = '(let [dt (php/new \\DateTimeImmutable)] (.for';
        $context = $this->resolver->resolve($source . ' dt))', 1, strlen($source) + 1);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
        self::assertSame('for', $context->prefix);
    }

    public function test_dot_field_resolves_the_receiver_after_the_cursor(): void
    {
        $source = '(let [dt (php/new \\DateTimeImmutable)] (.-';
        $context = $this->resolver->resolve($source . 'x dt))', 1, strlen($source) + 1);

        self::assertSame(PhpInteropContext::KIND_INSTANCE_MEMBER, $context->kind);
        self::assertSame('DateTimeImmutable', $context->class);
    }

    public function test_dot_member_without_a_receiver_is_none(): void
    {
        $context = $this->resolveAtEnd('(.for');

        self::assertTrue($context->isNone());
    }

    private function resolveAtEnd(string $source): PhpInteropContext
    {
        $lastNewline = strrpos($source, "\n");
        $line = substr_count($source, "\n") + 1;
        $col = ($lastNewline === false ? strlen($source) : strlen($source) - $lastNewline - 1) + 1;

        return $this->resolver->resolve($source, $line, $col);
    }
}
