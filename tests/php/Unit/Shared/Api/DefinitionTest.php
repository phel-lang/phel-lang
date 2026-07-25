<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Api;

use Phel\Shared\Api\Definition;
use PHPUnit\Framework\TestCase;

final class DefinitionTest extends TestCase
{
    public function test_it_builds_full_name_from_namespace_and_name(): void
    {
        $definition = $this->makeDefinition('user\\foo', 'bar');

        self::assertSame('user\\foo/bar', $definition->fullName());
    }

    public function test_it_serializes_to_array(): void
    {
        $definition = new Definition(
            namespace: 'user\\foo',
            name: 'bar',
            uri: 'foo.phel',
            line: 3,
            col: 5,
            kind: Definition::KIND_DEFN,
            signature: ['[x y]'],
            docstring: 'adds',
            private: false,
        );

        self::assertSame([
            'namespace' => 'user\\foo',
            'name' => 'bar',
            'uri' => 'foo.phel',
            'line' => 3,
            'col' => 5,
            'kind' => 'defn',
            'signature' => ['[x y]'],
            'docstring' => 'adds',
            'private' => false,
            'deprecated' => '',
        ], $definition->toArray());
    }

    public function test_it_serializes_the_deprecation_reason(): void
    {
        $definition = new Definition(
            namespace: 'phel\\core',
            name: 'set-meta!',
            uri: 'core.phel',
            line: 1,
            col: 1,
            kind: Definition::KIND_DEF,
            signature: [],
            docstring: '',
            private: false,
            deprecated: '0.32.0',
        );

        self::assertTrue($definition->isDeprecated());
        self::assertSame('0.32.0', $definition->toArray()['deprecated']);
    }

    public function test_a_definition_without_the_metadata_is_not_deprecated(): void
    {
        self::assertFalse($this->makeDefinition('user', 'bar')->isDeprecated());
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
