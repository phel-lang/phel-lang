<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Extractor;

use Phel\Build\Extractor\TopologicalNamespaceSorter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TopologicalSortingTest extends TestCase
{
    private TopologicalNamespaceSorter $sorter;

    public function setUp(): void
    {
        $this->sorter = new TopologicalNamespaceSorter();
    }

    public function test_circular_exception(): void
    {
        $this->expectException(RuntimeException::class);

        $data = ['car', 'owner'];
        $dependencies = [
            'car' => ['owner'],
            'owner' => ['car'],
        ];

        $this->sorter->sort($data, $dependencies);
    }

    public function test_simplesort(): void
    {
        $data = ['car', 'owner'];
        $dependencies = [
            'car' => ['owner'],
            'owner' => [],
        ];

        $sorted = $this->sorter->sort($data, $dependencies);

        self::assertEquals(['owner', 'car'], $sorted);
    }

    public function test_multiple_dependencies(): void
    {
        $data = ['car', 'owner', 'brand'];
        $dependencies = [
            'car' => ['owner', 'brand'],
            'owner' => ['brand'],
        ];

        $sorted = $this->sorter->sort($data, $dependencies);

        self::assertEquals(['brand', 'owner', 'car'], $sorted);
    }

    public function test_duplicated_data_entries(): void
    {
        $data = ['car', 'owner', 'brand', 'owner'];
        $dependencies = [
            'car' => ['owner', 'brand'],
            'owner' => ['brand'],
        ];

        $sorted = $this->sorter->sort($data, $dependencies);

        self::assertEquals(['brand', 'owner', 'car'], $sorted);
    }
}
