<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Transfer;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\ProjectIndex;
use PHPUnit\Framework\TestCase;

final class ProjectIndexTest extends TestCase
{
    public function test_it_reports_namespace_and_definition_counts(): void
    {
        $definitions = [
            'user\\foo/a' => $this->makeDefinition('user\\foo', 'a'),
            'user\\foo/b' => $this->makeDefinition('user\\foo', 'b'),
            'user\\bar/c' => $this->makeDefinition('user\\bar', 'c'),
        ];

        $index = new ProjectIndex($definitions);

        self::assertSame(3, $index->countDefinitions());
        self::assertSame(2, $index->countNamespaces());
        self::assertSame(['user\\foo', 'user\\bar'], $index->namespaces());
    }

    public function test_it_filters_definitions_by_namespace(): void
    {
        $definitions = [
            'user\\foo/a' => $this->makeDefinition('user\\foo', 'a'),
            'user\\bar/c' => $this->makeDefinition('user\\bar', 'c'),
        ];

        $index = new ProjectIndex($definitions);

        self::assertCount(1, $index->definitionsInNamespace('user\\foo'));
        self::assertCount(1, $index->definitionsInNamespace('user\\bar'));
        self::assertCount(0, $index->definitionsInNamespace('unknown'));
    }

    public function test_it_serializes_to_array_including_references(): void
    {
        $index = new ProjectIndex(
            ['user\\foo/a' => $this->makeDefinition('user\\foo', 'a')],
            ['user\\foo/a' => [new Location('foo.phel', 1, 2)]],
        );

        $arr = $index->toArray();
        self::assertSame(1, $arr['definitions']);
        self::assertArrayHasKey('symbols', $arr);
        self::assertArrayHasKey('user\\foo/a', $arr['references']);
    }

    private function makeDefinition(string $namespace, string $name): Definition
    {
        return new Definition(
            namespace: $namespace,
            name: $name,
            uri: 'x.phel',
            line: 1,
            col: 1,
            kind: Definition::KIND_DEFN,
            signature: [],
            docstring: '',
            private: false,
        );
    }
}
