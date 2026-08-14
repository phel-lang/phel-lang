<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Gacela;
use Phel\Api\Application\LocalBindingResolver;
use Phel\Compiler\CompilerFacade;
use Phel\Shared\Api\Location;
use PHPUnit\Framework\TestCase;

final class LocalBindingResolverTest extends TestCase
{
    private LocalBindingResolver $resolver;

    protected function setUp(): void
    {
        CacheableConfig::reset();
        Gacela::bootstrap(__DIR__);
        $this->resolver = new LocalBindingResolver(new CompilerFacade());
    }

    protected function tearDown(): void
    {
        CacheableConfig::reset();
    }

    public function test_it_resolves_a_body_usage_to_the_let_binding(): void
    {
        $location = $this->resolve(
            "(let [mysym 123]\n    mysym)",
            line: 2,
            col: 5,
            word: 'mysym',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame('file:///demo.phel', $location->uri);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
        self::assertSame(12, $location->endCol);
    }

    public function test_it_prefers_the_innermost_shadowing_binding(): void
    {
        $location = $this->resolve(
            "(let [a 1]\n  (let [a 2]\n    a))",
            line: 3,
            col: 5,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(2, $location->line);
        self::assertSame(9, $location->col);
    }

    public function test_a_later_binding_init_sees_an_earlier_binding(): void
    {
        $location = $this->resolve(
            "(let [a 1\n      b a]\n  b)",
            line: 2,
            col: 9,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
    }

    public function test_an_inner_init_sees_an_outer_binding(): void
    {
        $location = $this->resolve(
            "(let [a 1]\n  (let [b a]\n    b))",
            line: 2,
            col: 11,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
    }

    public function test_a_repeated_name_shadows_an_earlier_binding_after_its_init(): void
    {
        $source = "(let [a 1\n      a a]\n  a)";

        $init = $this->resolve($source, line: 2, col: 9, word: 'a');
        self::assertInstanceOf(Location::class, $init);
        self::assertSame(1, $init->line);
        self::assertSame(7, $init->col);

        $body = $this->resolve($source, line: 3, col: 3, word: 'a');
        self::assertInstanceOf(Location::class, $body);
        self::assertSame(2, $body->line);
        self::assertSame(7, $body->col);
    }

    public function test_a_binding_is_not_in_scope_for_its_own_init(): void
    {
        $location = $this->resolve(
            "(let [a a]\n  a)",
            line: 1,
            col: 9,
            word: 'a',
        );

        self::assertNull($location);
    }

    public function test_the_binding_site_resolves_to_itself(): void
    {
        // Falling through would hand the request to the project index, which
        // scans every namespace by bare name and would jump to an unrelated
        // global of the same name.
        $location = $this->resolve(
            "(let [mysym 123]\n    mysym)",
            line: 1,
            col: 7,
            word: 'mysym',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
        self::assertSame(12, $location->endCol);
    }

    public function test_a_caret_just_past_the_last_character_still_resolves(): void
    {
        // Document::wordAt() reports the word at this caret, so the resolver
        // has to agree or navigation dies where editors leave the caret.
        $location = $this->resolve(
            "(let [mysym 1]\n  mysym)",
            line: 2,
            col: 8,
            word: 'mysym',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
    }

    public function test_the_end_column_counts_codepoints_not_bytes(): void
    {
        $location = $this->resolve(
            "(let [café 1]\n  café)",
            line: 2,
            col: 3,
            word: 'café',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(7, $location->col);
        self::assertSame(11, $location->endCol);
    }

    public function test_a_qualified_symbol_does_not_resolve_to_a_local(): void
    {
        $location = $this->resolve(
            "(let [a 1]\n  foo/a)",
            line: 2,
            col: 3,
            word: 'foo/a',
        );

        self::assertNull($location);
    }

    public function test_an_or_default_expression_does_not_shadow_the_binding(): void
    {
        $location = $this->resolve(
            "(let [a 1 {:keys [b] :or {b a}} m]\n  a)",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
    }

    public function test_a_namespaced_let_head_is_not_a_binding_form(): void
    {
        // The compiler dispatches special forms by their full name, so a
        // qualified my/let is a plain call, not the let special form.
        $location = $this->resolve(
            "(my/let [a 1]\n  a)",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertNull($location);
    }

    public function test_it_resolves_a_loop_body_usage_to_the_loop_binding(): void
    {
        $location = $this->resolve(
            "(loop [x 0]\n  x)",
            line: 2,
            col: 3,
            word: 'x',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(8, $location->col);
    }

    public function test_vector_destructuring_binds_each_name(): void
    {
        $location = $this->resolve(
            "(let [[a b] pair]\n  b)",
            line: 2,
            col: 3,
            word: 'b',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(10, $location->col);
    }

    public function test_map_destructuring_binds_keys_names(): void
    {
        $location = $this->resolve(
            "(let [{:keys [a]} m]\n  a)",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(15, $location->col);
    }

    public function test_map_destructuring_binds_the_as_name(): void
    {
        $location = $this->resolve(
            "(let [{:as m} x]\n  m)",
            line: 2,
            col: 3,
            word: 'm',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(12, $location->col);
    }

    public function test_map_destructuring_binds_a_name_for_a_direct_key(): void
    {
        $location = $this->resolve(
            "(let [{:name name} person]\n  name)",
            line: 2,
            col: 3,
            word: 'name',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(14, $location->col);
    }

    public function test_nested_destructuring_binds_names_recursively(): void
    {
        $location = $this->resolve(
            "(let [[{:keys [name]}] rows]\n  name)",
            line: 2,
            col: 3,
            word: 'name',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(16, $location->col);
    }

    public function test_vector_rest_destructuring_binds_the_rest_name(): void
    {
        $location = $this->resolve(
            "(let [[head & tail] values]\n  tail)",
            line: 2,
            col: 3,
            word: 'tail',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(15, $location->col);
    }

    public function test_a_non_vector_binding_form_degrades_to_no_match(): void
    {
        $location = $this->resolve(
            '(let a b)',
            line: 1,
            col: 8,
            word: 'b',
        );

        self::assertNull($location);
    }

    public function test_an_incomplete_buffer_degrades_to_no_match(): void
    {
        $location = $this->resolve(
            "(let [a 1]\n  a",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertNull($location);
    }

    public function test_scope_does_not_leak_across_top_level_forms(): void
    {
        // The def name is before the let, so its `a` must not resolve to it.
        $before = $this->resolve(
            "(def a 9)\n(let [a 1]\n  a)",
            line: 1,
            col: 6,
            word: 'a',
        );

        self::assertNull($before);

        // The body usage resolves to the let in its own top-level form.
        $body = $this->resolve(
            "(def z 9)\n(let [a 1]\n  (+ a z))",
            line: 3,
            col: 6,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $body);
        self::assertSame(2, $body->line);
        self::assertSame(7, $body->col);
    }

    public function test_a_usage_inside_a_set_literal_resolves(): void
    {
        $location = $this->resolve(
            "(let [a 1]\n  #{a})",
            line: 2,
            col: 5,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
    }

    public function test_a_quoted_binding_form_binds_nothing(): void
    {
        // Quoted forms are inert data, so neither the quoted `let` nor the
        // enclosing one may claim the usage.
        $location = $this->resolve(
            "(let [a 1]\n  '(let [a 2] a))",
            line: 2,
            col: 15,
            word: 'a',
        );

        self::assertNull($location);
    }

    public function test_an_or_default_expression_resolves_to_the_enclosing_binding(): void
    {
        $location = $this->resolve(
            "(let [a 1 {:keys [b] :or {b a}} m]\n  b)",
            line: 1,
            col: 29,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
    }

    public function test_an_earlier_init_does_not_see_a_later_binding(): void
    {
        $location = $this->resolve(
            "(let [a b\n      b 1]\n  a)",
            line: 1,
            col: 9,
            word: 'b',
        );

        self::assertNull($location);
    }

    public function test_a_trailing_unpaired_binding_is_ignored(): void
    {
        $location = $this->resolve(
            "(let [a 1 b]\n  a)",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(7, $location->col);
    }

    public function test_an_empty_source_resolves_to_nothing(): void
    {
        self::assertNull($this->resolve('', line: 1, col: 1, word: 'a'));
    }

    public function test_a_qualified_word_is_rejected_without_parsing(): void
    {
        // Symbol::getName() never contains a `/`, so the whole-buffer parse
        // this would otherwise pay for can only ever return null.
        self::assertNull($this->resolve('(let [a 1] a)', line: 1, col: 12, word: 'foo/a'));
    }

    public function test_if_let_binds_its_then_branch(): void
    {
        $location = $this->resolve(
            "(if-let [a (f)]\n  a\n  a)",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(10, $location->col);
    }

    public function test_if_let_does_not_bind_its_else_branch(): void
    {
        // The macro expands `else` outside the inner let, so the binding is
        // not in scope there.
        $location = $this->resolve(
            "(if-let [a (f)]\n  a\n  a)",
            line: 3,
            col: 3,
            word: 'a',
        );

        self::assertNull($location);
    }

    public function test_when_let_binds_its_body(): void
    {
        $location = $this->resolve(
            "(when-let [a (f)]\n  a)",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(12, $location->col);
    }

    public function test_when_first_binds_its_body(): void
    {
        $location = $this->resolve(
            "(when-first [a xs]\n  a)",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(14, $location->col);
    }

    public function test_a_fn_parameter_shadows_an_outer_binding(): void
    {
        $location = $this->resolve(
            "(let [a 1]\n  (fn [a] a))",
            line: 2,
            col: 11,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(2, $location->line);
        self::assertSame(8, $location->col);
    }

    public function test_a_fn_parameter_resolves_without_an_enclosing_let(): void
    {
        $location = $this->resolve(
            "(fn [a]\n  a)",
            line: 2,
            col: 3,
            word: 'a',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(1, $location->line);
        self::assertSame(6, $location->col);
    }

    public function test_foreach_binds_its_body_but_not_its_collection(): void
    {
        $source = "(let [xs 1]\n  (foreach [a xs] a))";

        $body = $this->resolve($source, line: 2, col: 19, word: 'a');
        self::assertInstanceOf(Location::class, $body);
        self::assertSame(2, $body->line);
        self::assertSame(13, $body->col);

        // The trailing element is the collection expression, so it still sees
        // the enclosing scope.
        $collection = $this->resolve($source, line: 2, col: 15, word: 'xs');
        self::assertInstanceOf(Location::class, $collection);
        self::assertSame(1, $collection->line);
        self::assertSame(7, $collection->col);
    }

    public function test_catch_binds_its_exception(): void
    {
        $location = $this->resolve(
            "(try\n  (catch \\Exception e e))",
            line: 2,
            col: 23,
            word: 'e',
        );

        self::assertInstanceOf(Location::class, $location);
        self::assertSame(2, $location->line);
        self::assertSame(21, $location->col);
    }

    public function test_a_defn_parameter_hides_an_outer_binding(): void
    {
        // defn may be multi-arity, so its parameters are not modelled; the
        // outer binding must still not be offered in its place.
        $location = $this->resolve(
            "(let [a 1]\n  (defn f [a] a))",
            line: 2,
            col: 15,
            word: 'a',
        );

        self::assertNull($location);
    }

    public function test_a_for_binding_hides_an_outer_binding(): void
    {
        $location = $this->resolve(
            "(let [a 1]\n  (for [a :in xs] a))",
            line: 2,
            col: 19,
            word: 'a',
        );

        self::assertNull($location);
    }

    public function test_a_rebound_dynamic_var_hides_an_outer_binding(): void
    {
        $location = $this->resolve(
            "(let [a 1]\n  (binding [a 2] a))",
            line: 2,
            col: 18,
            word: 'a',
        );

        self::assertNull($location);
    }

    private function resolve(string $source, int $line, int $col, string $word): ?Location
    {
        return $this->resolver->resolve($source, 'file:///demo.phel', $line, $col, $word);
    }
}
