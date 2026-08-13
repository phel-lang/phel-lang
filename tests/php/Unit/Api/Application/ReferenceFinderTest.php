<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\ReferenceFinder;
use Phel\Shared\Api\Location;
use Phel\Shared\Api\ProjectIndex;
use PHPUnit\Framework\TestCase;

final class ReferenceFinderTest extends TestCase
{
    public function test_it_returns_matching_references_for_qualified_key(): void
    {
        $finder = new ReferenceFinder();
        $index = new ProjectIndex(
            [],
            ['user\\foo/bar' => [new Location('x.phel', 10, 2)]],
        );

        $refs = $finder->find($index, 'user\\foo', 'bar');

        self::assertCount(1, $refs);
        self::assertSame(10, $refs[0]->line);
    }

    public function test_it_returns_empty_when_no_references_known(): void
    {
        $finder = new ReferenceFinder();
        $index = new ProjectIndex([], []);

        self::assertSame([], $finder->find($index, 'user', 'unknown'));
    }

    public function test_it_does_not_return_the_namespace_declaration_for_an_empty_symbol(): void
    {
        // SymbolKey::resolve('phel.pprint', '') builds 'phel.pprint/', so storing the
        // ns declaration under that key would hand rename an edit over a foreign (ns ...)
        $finder = new ReferenceFinder();
        $index = new ProjectIndex(
            [],
            [],
            ['phel.pprint' => new Location('pprint.phel', 1, 5, 1, 16)],
        );

        self::assertSame([], $finder->find($index, 'phel.pprint', ''));
    }

    public function test_it_falls_back_to_unqualified_reference_key(): void
    {
        $finder = new ReferenceFinder();
        $index = new ProjectIndex(
            [],
            ['bar' => [new Location('x.phel', 1, 1)]],
        );

        $refs = $finder->find($index, 'user\\foo', 'bar');

        self::assertCount(1, $refs);
    }
}
