<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\LoadOrderResolver;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

final class LoadOrderResolverTest extends TestCase
{
    public function test_returns_the_transitive_closure_in_global_order_ending_with_the_target(): void
    {
        $core = new NamespaceInformation('/core.phel', 'phel.core', []);
        $str = new NamespaceInformation('/string.phel', 'phel.string', ['phel.core']);
        $lib = new NamespaceInformation('/lib.phel', 'app.lib', ['phel.core', 'phel.string']);
        $other = new NamespaceInformation('/other.phel', 'app.other', ['phel.core']);
        $test = new NamespaceInformation('/lib_test.phel', 'app.lib-test', ['phel.test', 'app.lib']);
        $phelTest = new NamespaceInformation('/test.phel', 'phel.test', ['phel.core']);

        $resolver = new LoadOrderResolver([$core, $str, $phelTest, $lib, $other, $test]);

        self::assertSame(
            [
                ['ns' => 'phel.core', 'file' => '/core.phel'],
                ['ns' => 'phel.string', 'file' => '/string.phel'],
                ['ns' => 'phel.test', 'file' => '/test.phel'],
                ['ns' => 'app.lib', 'file' => '/lib.phel'],
                ['ns' => 'app.lib-test', 'file' => '/lib_test.phel'],
            ],
            $resolver->loadOrderFor($test),
        );
    }

    public function test_a_namespace_without_dependencies_loads_only_itself(): void
    {
        $lib = new NamespaceInformation('/lib.phel', 'app.lib', []);
        $leaf = new NamespaceInformation('/leaf.phel', 'app.leaf', []);

        $resolver = new LoadOrderResolver([$lib, $leaf]);

        self::assertSame([['ns' => 'app.leaf', 'file' => '/leaf.phel']], $resolver->loadOrderFor($leaf));
    }

    public function test_every_bundled_phel_namespace_is_loaded_even_when_not_required(): void
    {
        $core = new NamespaceInformation('/core.phel', 'phel.core', []);
        $json = new NamespaceInformation('/json.phel', 'phel.json', ['phel.core']);
        $lib = new NamespaceInformation('/lib.phel', 'app.lib', ['phel.core']);
        $fqn = new NamespaceInformation('/fqn.phel', 'app.fqn', ['phel.core']);

        $resolver = new LoadOrderResolver([$core, $json, $lib, $fqn]);

        self::assertSame(
            [
                ['ns' => 'phel.core', 'file' => '/core.phel'],
                ['ns' => 'phel.json', 'file' => '/json.phel'],
                ['ns' => 'app.fqn', 'file' => '/fqn.phel'],
            ],
            $resolver->loadOrderFor($fqn),
        );
    }

    public function test_an_unknown_dependency_is_ignored_and_a_cycle_terminates(): void
    {
        $a = new NamespaceInformation('/a.phel', 'app.a', ['app.b', 'vendor.missing']);
        $b = new NamespaceInformation('/b.phel', 'app.b', ['app.a']);

        $resolver = new LoadOrderResolver([$a, $b]);

        self::assertSame(
            [
                ['ns' => 'app.a', 'file' => '/a.phel'],
                ['ns' => 'app.b', 'file' => '/b.phel'],
            ],
            $resolver->loadOrderFor($b),
        );
    }
}
