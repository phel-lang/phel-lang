<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Extractor;

use Phel\Build\Extractor\TopologicalSorting;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TopologicalSortingTest extends TestCase
{
    public function test_circular_exception(): void
    {
        $this->expectException(RuntimeException::class);

        $data = ['car', 'owner'];
        $dependencies = [
            'car' => ['owner'],
            'owner' => ['car'],
        ];
        $sorter = new TopologicalSorting();
        $sorter->sort($data, $dependencies);
    }

    public function test_simplesort(): void
    {
        $data = ['car', 'owner'];
        $dependencies = [
            'car' => ['owner'],
            'owner' => [],
        ];
        $sorter = new TopologicalSorting();
        $sorted = $sorter->sort($data, $dependencies);

        $this->assertEquals(['owner', 'car'], $sorted);
    }

    public function test_multiple_dependencies(): void
    {
        $data = ['car', 'owner', 'brand'];
        $dependencies = [
            'car' => ['owner', 'brand'],
            'owner' => ['brand'],
        ];
        $sorter = new TopologicalSorting();
        $sorted = $sorter->sort($data, $dependencies);

        $this->assertEquals(['brand', 'owner', 'car'], $sorted);
    }

    public function test_duplicated_data_entries(): void
    {
        $data = ['car', 'owner', 'brand', 'owner'];
        $dependencies = [
            'car' => ['owner', 'brand'],
            'owner' => ['brand'],
        ];
        $sorter = new TopologicalSorting();
        $sorted = $sorter->sort($data, $dependencies);

        $this->assertEquals(['brand', 'owner', 'car'], $sorted);
    }
}
