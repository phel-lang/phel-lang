<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Symbol;
use PHPUnit\Framework\TestCase;
use ReflectionObject;

use function sprintf;

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

    public function test_with_local_and_shadow_matches_rebuild_for_distinct_bindings(): void
    {
        $pairs = [
            [Symbol::create('a'), Symbol::create('a_1')],
            [Symbol::create('b'), Symbol::create('b_1')],
            [Symbol::create('c'), Symbol::create('c_1')],
        ];

        $this->assertIncrementalEqualsRebuild($pairs);
    }

    public function test_with_local_and_shadow_matches_rebuild_when_rebinding_same_name(): void
    {
        $pairs = [
            [Symbol::create('a'), Symbol::create('a_1')],
            [Symbol::create('a'), Symbol::create('a_2')],
        ];

        $env = $this->assertIncrementalEqualsRebuild($pairs);

        // The last shadow wins; the earlier shadow name is no longer reachable.
        self::assertSame('a', $env->findLocalByShadowedName('a_2')?->getName());
        self::assertNull($env->findLocalByShadowedName('a_1'));
        self::assertCount(1, $env->getLocals());
    }

    public function test_with_local_and_shadow_matches_rebuild_for_colliding_shadow_names(): void
    {
        $pairs = [
            [Symbol::create('a'), Symbol::create('x')],
            [Symbol::create('b'), Symbol::create('x')],
        ];

        $env = $this->assertIncrementalEqualsRebuild($pairs);

        // First binding wins the colliding shadow name in the reverse index.
        self::assertSame('a', $env->findLocalByShadowedName('x')?->getName());
        self::assertCount(2, $env->getLocals());
    }

    public function test_with_locals_and_shadows_batch_matches_per_pair(): void
    {
        $pairs = [
            [Symbol::create('a'), Symbol::create('a_1')],
            [Symbol::create('a'), Symbol::create('a_2')],
            [Symbol::create('b'), Symbol::create('a_1')],
            [Symbol::create('c'), Symbol::create('c_1')],
        ];

        $perPair = NodeEnvironment::empty();
        foreach ($pairs as [$local, $shadow]) {
            $perPair = $perPair->withLocalAndShadow($local, $shadow);
        }

        $batch = NodeEnvironment::empty()->withLocalsAndShadows($pairs);

        $this->assertEnvIndexesEqual($perPair, $batch);
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

    /**
     * Builds an env through the incremental `withLocalAndShadow()` path and
     * through the legacy chained `withMergedLocals()->withShadowedLocal()`
     * rebuild path, asserting the four derived state arrays are identical.
     *
     * @param list<array{Symbol, Symbol}> $pairs
     */
    private function assertIncrementalEqualsRebuild(array $pairs): NodeEnvironmentInterface
    {
        $incremental = NodeEnvironment::empty();
        $rebuilt = NodeEnvironment::empty();

        foreach ($pairs as [$local, $shadow]) {
            $incremental = $incremental->withLocalAndShadow($local, $shadow);
            $rebuilt = $rebuilt->withMergedLocals([$local])->withShadowedLocal($local, $shadow);
        }

        $this->assertEnvIndexesEqual($rebuilt, $incremental);

        return $incremental;
    }

    private function assertEnvIndexesEqual(
        NodeEnvironmentInterface $expected,
        NodeEnvironmentInterface $actual,
    ): void {
        foreach (['locals', 'localsByName', 'shadowed', 'shadowedReverse'] as $property) {
            self::assertSame(
                $this->readPrivate($expected, $property),
                $this->readPrivate($actual, $property),
                sprintf('Index "%s" diverged from the rebuild path', $property),
            );
        }
    }

    private function readPrivate(NodeEnvironmentInterface $env, string $property): mixed
    {
        $reflection = new ReflectionObject($env);

        return $reflection->getProperty($property)->getValue($env);
    }
}
