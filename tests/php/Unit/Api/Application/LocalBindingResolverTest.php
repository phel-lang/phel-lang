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

    private function resolve(string $source, int $line, int $col, string $word): ?Location
    {
        return $this->resolver->resolve($source, 'file:///demo.phel', $line, $col, $word);
    }
}
