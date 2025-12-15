<?php

declare(strict_types=1);

namespace PhelTest\Unit\Build\Application;

use Phel\Build\Application\FileSetDiffCalculator;
use Phel\Build\Domain\Graph\FileSetSnapshot;
use PHPUnit\Framework\TestCase;

final class FileSetDiffCalculatorTest extends TestCase
{
    private FileSetDiffCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new FileSetDiffCalculator();
    }

    public function test_no_cache_treats_all_files_as_added(): void
    {
        $currentFiles = [
            '/path/a.phel' => 1000,
            '/path/b.phel' => 2000,
        ];

        $diff = $this->calculator->calculate(null, $currentFiles);

        self::assertSame(['/path/a.phel', '/path/b.phel'], $diff->added);
        self::assertSame([], $diff->modified);
        self::assertSame([], $diff->deleted);
        self::assertFalse($diff->isEmpty());
    }

    public function test_identical_file_sets_return_empty_diff(): void
    {
        $cached = new FileSetSnapshot(
            ['/path/a.phel' => 1000, '/path/b.phel' => 2000],
            ['/path'],
            time(),
        );
        $currentFiles = [
            '/path/a.phel' => 1000,
            '/path/b.phel' => 2000,
        ];

        $diff = $this->calculator->calculate($cached, $currentFiles);

        self::assertTrue($diff->isEmpty());
        self::assertSame([], $diff->added);
        self::assertSame([], $diff->modified);
        self::assertSame([], $diff->deleted);
    }

    public function test_detects_new_file(): void
    {
        $cached = new FileSetSnapshot(
            ['/path/a.phel' => 1000],
            ['/path'],
            time(),
        );
        $currentFiles = [
            '/path/a.phel' => 1000,
            '/path/b.phel' => 2000, // new file
        ];

        $diff = $this->calculator->calculate($cached, $currentFiles);

        self::assertSame(['/path/b.phel'], $diff->added);
        self::assertSame([], $diff->modified);
        self::assertSame([], $diff->deleted);
    }

    public function test_detects_modified_file(): void
    {
        $cached = new FileSetSnapshot(
            ['/path/a.phel' => 1000, '/path/b.phel' => 2000],
            ['/path'],
            time(),
        );
        $currentFiles = [
            '/path/a.phel' => 1000,
            '/path/b.phel' => 3000, // modified (different mtime)
        ];

        $diff = $this->calculator->calculate($cached, $currentFiles);

        self::assertSame([], $diff->added);
        self::assertSame(['/path/b.phel'], $diff->modified);
        self::assertSame([], $diff->deleted);
    }

    public function test_detects_deleted_file(): void
    {
        $cached = new FileSetSnapshot(
            ['/path/a.phel' => 1000, '/path/b.phel' => 2000],
            ['/path'],
            time(),
        );
        $currentFiles = [
            '/path/a.phel' => 1000,
            // b.phel deleted
        ];

        $diff = $this->calculator->calculate($cached, $currentFiles);

        self::assertSame([], $diff->added);
        self::assertSame([], $diff->modified);
        self::assertSame(['/path/b.phel'], $diff->deleted);
    }

    public function test_detects_multiple_changes(): void
    {
        $cached = new FileSetSnapshot(
            [
                '/path/a.phel' => 1000,
                '/path/b.phel' => 2000,
                '/path/c.phel' => 3000,
            ],
            ['/path'],
            time(),
        );
        $currentFiles = [
            '/path/a.phel' => 1000, // unchanged
            '/path/b.phel' => 2500, // modified
            // c.phel deleted
            '/path/d.phel' => 4000, // added
        ];

        $diff = $this->calculator->calculate($cached, $currentFiles);

        self::assertSame(['/path/d.phel'], $diff->added);
        self::assertSame(['/path/b.phel'], $diff->modified);
        self::assertSame(['/path/c.phel'], $diff->deleted);
        self::assertFalse($diff->isEmpty());
    }

    public function test_get_changed_files_returns_added_and_modified(): void
    {
        $cached = new FileSetSnapshot(
            ['/path/a.phel' => 1000],
            ['/path'],
            time(),
        );
        $currentFiles = [
            '/path/a.phel' => 2000, // modified
            '/path/b.phel' => 3000, // added
        ];

        $diff = $this->calculator->calculate($cached, $currentFiles);

        self::assertCount(2, $diff->getChangedFiles());
        self::assertContains('/path/a.phel', $diff->getChangedFiles());
        self::assertContains('/path/b.phel', $diff->getChangedFiles());
    }
}
