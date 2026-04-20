<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lsp\Application\Handler;

use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Lsp\Application\Handler\SymbolResolver;
use PHPUnit\Framework\TestCase;

final class SymbolResolverTest extends TestCase
{
    public function test_split_returns_namespaced_tuple_when_word_has_slash(): void
    {
        $resolver = new SymbolResolver();
        $index = new ProjectIndex([]);

        self::assertSame(
            ['my-ns', 'fn-name'],
            $resolver->split('my-ns/fn-name', $index),
        );
    }

    public function test_split_returns_empty_name_when_slash_but_no_tail(): void
    {
        $resolver = new SymbolResolver();
        $index = new ProjectIndex([]);

        self::assertSame(['ns', ''], $resolver->split('ns/', $index));
    }

    public function test_split_resolves_bare_name_against_index(): void
    {
        $def = new Definition('phel\\core', 'map', 'file:///core.phel', 1, 1, Definition::KIND_DEFN, [], '', false);
        $index = new ProjectIndex(['phel\\core/map' => $def]);
        $resolver = new SymbolResolver();

        self::assertSame(['phel\\core', 'map'], $resolver->split('map', $index));
    }

    public function test_split_returns_empty_namespace_for_unknown_bare_name(): void
    {
        $resolver = new SymbolResolver();
        $index = new ProjectIndex([]);

        self::assertSame(['', 'unknown'], $resolver->split('unknown', $index));
    }

    public function test_find_resolves_fully_qualified_word_directly(): void
    {
        $def = new Definition('foo', 'bar', '', 1, 1, Definition::KIND_DEFN, [], '', false);
        $index = new ProjectIndex(['foo/bar' => $def]);
        $resolver = new SymbolResolver();

        self::assertSame($def, $resolver->find('foo/bar', $index));
    }

    public function test_find_returns_null_for_unknown_fully_qualified_word(): void
    {
        $resolver = new SymbolResolver();
        $index = new ProjectIndex([]);

        self::assertNull($resolver->find('nope/nope', $index));
    }

    public function test_find_scans_index_for_bare_name(): void
    {
        $def = new Definition('phel\\string', 'join', '', 1, 1, Definition::KIND_DEFN, [], '', false);
        $index = new ProjectIndex(['phel\\string/join' => $def]);
        $resolver = new SymbolResolver();

        self::assertSame($def, $resolver->find('join', $index));
    }

    public function test_find_returns_null_when_bare_name_missing(): void
    {
        $def = new Definition('ns', 'other', '', 1, 1, Definition::KIND_DEFN, [], '', false);
        $index = new ProjectIndex(['ns/other' => $def]);
        $resolver = new SymbolResolver();

        self::assertNull($resolver->find('missing', $index));
    }
}
