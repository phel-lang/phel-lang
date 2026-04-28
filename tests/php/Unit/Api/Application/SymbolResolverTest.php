<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\SymbolResolver;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;
use PHPUnit\Framework\TestCase;

final class SymbolResolverTest extends TestCase
{
    public function test_it_resolves_qualified_symbol(): void
    {
        $resolver = new SymbolResolver();
        $index = $this->indexWith([$this->makeDefinition('user\\foo', 'bar')]);

        $def = $resolver->resolve($index, 'user\\other', 'user\\foo/bar');

        self::assertNotNull($def);
        self::assertSame('user\\foo/bar', $def->fullName());
    }

    public function test_it_resolves_in_current_namespace(): void
    {
        $resolver = new SymbolResolver();
        $index = $this->indexWith([$this->makeDefinition('user\\foo', 'bar')]);

        $def = $resolver->resolve($index, 'user\\foo', 'bar');

        self::assertNotNull($def);
        self::assertSame('bar', $def->name);
    }

    public function test_it_falls_back_to_unqualified_lookup(): void
    {
        $resolver = new SymbolResolver();
        $index = $this->indexWith([$this->makeDefinition('user\\foo', 'unique-name')]);

        $def = $resolver->resolve($index, 'other', 'unique-name');

        self::assertNotNull($def);
    }

    public function test_it_returns_null_for_missing_symbol(): void
    {
        $resolver = new SymbolResolver();
        $index = $this->indexWith([]);

        self::assertNull($resolver->resolve($index, 'user', 'missing'));
    }

    /**
     * @param list<Definition> $definitions
     */
    private function indexWith(array $definitions): ProjectIndex
    {
        $map = [];
        foreach ($definitions as $def) {
            $map[$def->fullName()] = $def;
        }

        return new ProjectIndex($map);
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
