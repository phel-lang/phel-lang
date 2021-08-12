<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Test;

use Phel\Command\Compile\TopologicalSorting;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TopologicalSortingTest extends TestCase
{
    public function testCircularException(): void
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

    public function testSimplesort(): void
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

    public function testMultipleDependencies(): void
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

    public function testDuplicatedDataEntries(): void
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
