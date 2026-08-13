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

    public function test_the_binding_site_itself_is_not_a_usage(): void
    {
        $location = $this->resolve(
            "(let [mysym 123]\n    mysym)",
            line: 1,
            col: 7,
            word: 'mysym',
        );

        self::assertNull($location);
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

    private function resolve(string $source, int $line, int $col, string $word): ?Location
    {
        return $this->resolver->resolve($source, 'file:///demo.phel', $line, $col, $word);
    }
}
