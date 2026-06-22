<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;

final class NodeEnvironmentTest extends TestCase
{
    public function test_empty_has_no_locals(): void
    {
        $env = NodeEnvironment::empty();

        self::assertFalse($env->hasLocal(Symbol::create('a')));
    }

    public function test_with_locals_indexes_lookups(): void
    {
        $env = NodeEnvironment::empty()
            ->withLocals([Symbol::create('a'), Symbol::create('b')]);

        self::assertTrue($env->hasLocal(Symbol::create('a')));
        self::assertTrue($env->hasLocal(Symbol::create('b')));
        self::assertFalse($env->hasLocal(Symbol::create('c')));
    }

    public function test_with_locals_is_immutable(): void
    {
        $base = NodeEnvironment::empty()->withLocals([Symbol::create('a')]);
        $next = $base->withLocals([Symbol::create('b')]);

        self::assertTrue($base->hasLocal(Symbol::create('a')));
        self::assertFalse($base->hasLocal(Symbol::create('b')));
        self::assertTrue($next->hasLocal(Symbol::create('b')));
        self::assertFalse($next->hasLocal(Symbol::create('a')));
    }

    public function test_find_local_by_shadowed_name_returns_original_local(): void
    {
        $local = Symbol::create('x');
        $shadow = Symbol::create('x_1');

        $env = NodeEnvironment::empty()
            ->withLocals([$local])
            ->withShadowedLocal($local, $shadow);

        $found = $env->findLocalByShadowedName('x_1');

        self::assertNotNull($found);
        self::assertSame('x', $found->getName());
    }

    public function test_find_local_by_shadowed_name_returns_null_when_unknown(): void
    {
        $env = NodeEnvironment::empty();

        self::assertNull($env->findLocalByShadowedName('missing'));
    }

    public function test_find_local_by_shadowed_name_with_nested_shadowing(): void
    {
        $x = Symbol::create('x');
        $shadowInner = Symbol::create('x_2');
        $shadowOuter = Symbol::create('x_1');

        $outer = NodeEnvironment::empty()
            ->withLocals([$x])
            ->withShadowedLocal($x, $shadowOuter);
        $inner = $outer->withShadowedLocal($x, $shadowInner);

        // Inner shadow wins for the same original name.
        self::assertSame('x', $inner->findLocalByShadowedName('x_2')->getName());
        // Outer environment is untouched by the inner clone.
        self::assertSame('x', $outer->findLocalByShadowedName('x_1')->getName());
        self::assertNull($outer->findLocalByShadowedName('x_2'));
    }

    public function test_without_shadowed_locals_clears_reverse_lookup(): void
    {
        $local = Symbol::create('x');
        $shadow = Symbol::create('x_1');

        $env = NodeEnvironment::empty()
            ->withLocals([$local])
            ->withShadowedLocal($local, $shadow)
            ->withoutShadowedLocals([$local]);

        self::assertNull($env->findLocalByShadowedName('x_1'));
        self::assertNull($env->getShadowed($local));
    }

    public function test_with_merged_locals_keeps_unique_locals(): void
    {
        $env = NodeEnvironment::empty()
            ->withLocals([Symbol::create('a'), Symbol::create('b')])
            ->withMergedLocals([Symbol::create('b'), Symbol::create('c')]);

        self::assertTrue($env->hasLocal(Symbol::create('a')));
        self::assertTrue($env->hasLocal(Symbol::create('b')));
        self::assertTrue($env->hasLocal(Symbol::create('c')));
        self::assertCount(3, $env->getLocals());
    }

    public function test_with_context_returns_same_instance_when_unchanged(): void
    {
        $env = NodeEnvironment::empty();

        self::assertSame($env, $env->withContext($env->getContext()));
    }

    public function test_with_context_returns_new_instance_when_changed(): void
    {
        $env = NodeEnvironment::empty();
        $next = $env->withContext(NodeEnvironment::CONTEXT_EXPRESSION);

        self::assertNotSame($env, $next);
        self::assertSame(NodeEnvironment::CONTEXT_EXPRESSION, $next->getContext());
        self::assertSame(NodeEnvironment::CONTEXT_STATEMENT, $env->getContext());
    }

    public function test_with_use_global_reference_returns_same_instance_when_unchanged(): void
    {
        $env = NodeEnvironment::empty();

        self::assertSame($env, $env->withUseGlobalReference($env->useGlobalReference()));
    }

    public function test_with_use_global_reference_returns_new_instance_when_changed(): void
    {
        $env = NodeEnvironment::empty();
        $next = $env->withUseGlobalReference(true);

        self::assertNotSame($env, $next);
        self::assertTrue($next->useGlobalReference());
        self::assertFalse($env->useGlobalReference());
    }

    public function test_with_bound_to_returns_same_instance_when_unchanged(): void
    {
        $env = NodeEnvironment::empty();

        self::assertSame($env, $env->withBoundTo($env->getBoundTo()));
    }

    public function test_with_bound_to_returns_new_instance_when_changed(): void
    {
        $env = NodeEnvironment::empty();
        $next = $env->withBoundTo('user\\foo');

        self::assertNotSame($env, $next);
        self::assertSame('user\\foo', $next->getBoundTo());
        self::assertSame('', $env->getBoundTo());
    }

    public function test_return_inference_deferred_defaults_to_false(): void
    {
        $env = NodeEnvironment::empty();

        self::assertFalse($env->isReturnInferenceDeferred());
    }

    public function test_with_return_inference_deferred_returns_same_instance_when_unchanged(): void
    {
        $env = NodeEnvironment::empty();

        self::assertSame($env, $env->withReturnInferenceDeferred(false));
    }

    public function test_with_return_inference_deferred_returns_new_instance_when_changed(): void
    {
        $env = NodeEnvironment::empty();
        $next = $env->withReturnInferenceDeferred(true);

        self::assertNotSame($env, $next);
        self::assertTrue($next->isReturnInferenceDeferred());
        self::assertFalse($env->isReturnInferenceDeferred());
    }

    public function test_with_local_and_shadow_matches_rebuild_for_distinct_bindings(): void
    {
        $pairs = [
            [Symbol::create('a'), Symbol::create('a_1')],
            [Symbol::create('b'), Symbol::create('b_1')],
            [Symbol::create('c'), Symbol::create('c_1')],
        ];

        self::assertEquals($this->buildByRebuild($pairs), $this->buildByIncremental($pairs));
    }

    public function test_with_local_and_shadow_matches_rebuild_when_rebinding_same_name(): void
    {
        $pairs = [
            [Symbol::create('x'), Symbol::create('x_1')],
            [Symbol::create('x'), Symbol::create('x_2')],
        ];

        $incremental = $this->buildByIncremental($pairs);

        self::assertEquals($this->buildByRebuild($pairs), $incremental);
        // Last shadow wins for the forward map; only its reverse resolves.
        self::assertSame('x', $incremental->findLocalByShadowedName('x_2')?->getName());
        self::assertNull($incremental->findLocalByShadowedName('x_1'));
    }

    public function test_with_local_and_shadow_matches_rebuild_on_top_of_parent_scope(): void
    {
        $parent = NodeEnvironment::empty()
            ->withMergedLocals([Symbol::create('outer')])
            ->withShadowedLocal(Symbol::create('outer'), Symbol::create('outer_1'));

        $pairs = [
            [Symbol::create('a'), Symbol::create('a_9')],
            [Symbol::create('outer'), Symbol::create('outer_2')],
        ];

        self::assertEquals(
            $this->buildByRebuild($pairs, $parent),
            $this->buildByIncremental($pairs, $parent),
        );
    }

    /**
     * @param list<array{Symbol, Symbol}> $pairs
     */
    private function buildByRebuild(array $pairs, ?NodeEnvironmentInterface $base = null): NodeEnvironmentInterface
    {
        $env = $base ?? NodeEnvironment::empty();
        foreach ($pairs as [$local, $shadow]) {
            $env = $env->withMergedLocals([$local])->withShadowedLocal($local, $shadow);
        }

        return $env;
    }

    /**
     * @param list<array{Symbol, Symbol}> $pairs
     */
    private function buildByIncremental(array $pairs, ?NodeEnvironmentInterface $base = null): NodeEnvironmentInterface
    {
        $env = $base ?? NodeEnvironment::empty();
        foreach ($pairs as [$local, $shadow]) {
            $env = $env->withLocalAndShadow($local, $shadow);
        }

        return $env;
    }
}
