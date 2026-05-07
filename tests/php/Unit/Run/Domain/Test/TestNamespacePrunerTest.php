<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Test;

use Phel\Build\Domain\Extractor\NamespaceInformation;
use Phel\Run\Domain\Test\TestNamespacePruner;
use PHPUnit\Framework\TestCase;

final class TestNamespacePrunerTest extends TestCase
{
    public function test_no_patterns_returns_input_unchanged(): void
    {
        $infos = [
            $this->ns('phel.core', []),
            $this->ns('app.foo', ['phel.core']),
            $this->ns('app.bar', ['phel.core']),
        ];

        self::assertSame($infos, new TestNamespacePruner()->prune($infos, []));
    }

    public function test_single_segment_glob_keeps_only_matching_user_namespaces(): void
    {
        $infos = [
            $this->ns('phel.core', []),
            $this->ns('app.foo', ['phel.core']),
            $this->ns('app.bar', ['phel.core']),
        ];

        $pruned = new TestNamespacePruner()->prune($infos, ['app.foo']);
        $names = array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $pruned);

        self::assertSame(['phel.core', 'app.foo'], $names);
    }

    public function test_keeps_transitive_dependencies_of_matching_namespaces(): void
    {
        $infos = [
            $this->ns('phel.core', []),
            $this->ns('app.util', ['phel.core']),
            $this->ns('app.foo', ['app.util', 'phel.core']),
            $this->ns('app.bar', ['phel.core']),
        ];

        $pruned = new TestNamespacePruner()->prune($infos, ['app.foo']);
        $names = array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $pruned);

        self::assertSame(['phel.core', 'app.util', 'app.foo'], $names);
        self::assertNotContains('app.bar', $names, 'unrelated user ns must be pruned');
    }

    public function test_bundled_phel_namespaces_always_survive(): void
    {
        $infos = [
            $this->ns('phel.core', []),
            $this->ns('phel.json', ['phel.core']),
            $this->ns('app.foo', ['phel.core']),
            $this->ns('app.bar', ['phel.core']),
        ];

        $pruned = new TestNamespacePruner()->prune($infos, ['app.foo']);
        $names = array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $pruned);

        self::assertContains('phel.json', $names, 'bundled phel.* must stay so FQN access works');
    }

    public function test_double_star_glob_matches_any_run(): void
    {
        $infos = [
            $this->ns('phel.core', []),
            $this->ns('app.deep.nested.tests', ['phel.core']),
            $this->ns('app.shallow', ['phel.core']),
        ];

        $pruned = new TestNamespacePruner()->prune($infos, ['app.**']);
        $names = array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $pruned);

        self::assertContains('app.deep.nested.tests', $names);
        self::assertContains('app.shallow', $names);
    }

    public function test_single_star_does_not_cross_segments(): void
    {
        $infos = [
            $this->ns('phel.core', []),
            $this->ns('app.deep.nested', ['phel.core']),
            $this->ns('app.shallow', ['phel.core']),
        ];

        $pruned = new TestNamespacePruner()->prune($infos, ['app.*']);
        $names = array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $pruned);

        self::assertContains('app.shallow', $names);
        self::assertNotContains('app.deep.nested', $names, 'single * must stop at segment boundary');
    }

    public function test_backslash_namespaces_match_dotted_globs(): void
    {
        $infos = [
            $this->ns('phel.core', []),
            $this->ns('app\\foo', ['phel.core']),
        ];

        $pruned = new TestNamespacePruner()->prune($infos, ['app.foo']);
        $names = array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $pruned);

        self::assertContains('app\\foo', $names);
    }

    public function test_no_match_keeps_only_bundled(): void
    {
        $infos = [
            $this->ns('phel.core', []),
            $this->ns('phel.test', ['phel.core']),
            $this->ns('app.foo', ['phel.core']),
        ];

        $pruned = new TestNamespacePruner()->prune($infos, ['zzz_no_match']);
        $names = array_map(static fn(NamespaceInformation $i): string => $i->getNamespace(), $pruned);

        self::assertSame(['phel.core', 'phel.test'], $names);
    }

    /**
     * @param list<string> $deps
     */
    private function ns(string $name, array $deps): NamespaceInformation
    {
        return new NamespaceInformation('/tmp/' . str_replace(['.', '\\'], '_', $name) . '.phel', $name, $deps);
    }
}
