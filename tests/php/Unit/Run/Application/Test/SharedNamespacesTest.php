<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Application\Test;

use Phel\Run\Application\Test\SharedNamespaces;
use Phel\Shared\NamespaceInformation;
use PHPUnit\Framework\TestCase;

final class SharedNamespacesTest extends TestCase
{
    public function test_keeps_every_namespace_some_other_namespace_requires_in_order(): void
    {
        $core = new NamespaceInformation('/core.phel', 'phel.core', []);
        $lib = new NamespaceInformation('/lib.phel', 'app.lib', ['phel.core']);
        $fixture = new NamespaceInformation('/fixture.phel', 'app.fixture', ['phel.core']);
        $testA = new NamespaceInformation('/a_test.phel', 'app.a-test', ['app.lib', 'app.fixture']);
        $testB = new NamespaceInformation('/b_test.phel', 'app.b-test', ['app.fixture']);

        self::assertSame(
            [$core, $lib, $fixture],
            SharedNamespaces::of([$core, $lib, $fixture, $testA, $testB]),
        );
    }

    public function test_a_leaf_nobody_requires_is_not_shared(): void
    {
        $lone = new NamespaceInformation('/lone.phel', 'app.lone', []);

        self::assertSame([], SharedNamespaces::of([$lone]));
    }

    public function test_every_file_of_a_required_namespace_is_kept(): void
    {
        $core = new NamespaceInformation('/core.phel', 'phel.core', []);
        $corePart = new NamespaceInformation('/core/part.phel', 'phel.core', [], false);
        $test = new NamespaceInformation('/t.phel', 'app.t', ['phel.core']);

        self::assertSame([$core, $corePart], SharedNamespaces::of([$core, $corePart, $test]));
    }

    public function test_a_dependency_outside_the_list_does_not_matter(): void
    {
        $test = new NamespaceInformation('/t.phel', 'app.t', ['vendor.missing']);

        self::assertSame([], SharedNamespaces::of([$test]));
    }
}
