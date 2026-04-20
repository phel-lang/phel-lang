<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\ReferenceFinder;
use Phel\Api\Transfer\Location;
use Phel\Api\Transfer\ProjectIndex;
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
