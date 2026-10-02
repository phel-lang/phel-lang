<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\FrameworkNamespaces;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrameworkNamespacesTest extends TestCase
{
    public static function provideClojureTargets(): iterable
    {
        yield 'clojure.string' => ['clojure.string', 'phel.string'];
        yield 'clojure.set lives in phel.core' => ['clojure.set', 'phel.core'];
        yield 'backslash separator' => ['clojure\\test', 'phel.test'];
        yield 'not clojure' => ['phel.string', null];
        yield 'user namespace' => ['app.main', null];
    }

    #[DataProvider('provideClojureTargets')]
    public function test_clojure_target(string $namespace, ?string $expected): void
    {
        self::assertSame($expected, FrameworkNamespaces::clojureTarget($namespace));
    }

    public function test_is_phel(): void
    {
        self::assertTrue(FrameworkNamespaces::isPhel('phel.string'));
        self::assertTrue(FrameworkNamespaces::isPhel('phel\\json'));
        self::assertFalse(FrameworkNamespaces::isPhel('clojure.string'));
        self::assertFalse(FrameworkNamespaces::isPhel('phelix.core'));
    }
}
