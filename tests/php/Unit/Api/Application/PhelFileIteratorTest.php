<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhelFileIterator;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function iterator_to_array;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class PhelFileIteratorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phel_iter_' . uniqid('', true);
        mkdir($this->root, 0o755, true);
        mkdir($this->root . '/nested', 0o755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/a.phel');
        @unlink($this->root . '/nested/b.phel');
        @unlink($this->root . '/c.txt');
        @rmdir($this->root . '/nested');
        @rmdir($this->root);
    }

    public function test_it_yields_phel_files_recursively(): void
    {
        file_put_contents($this->root . '/a.phel', '');
        file_put_contents($this->root . '/nested/b.phel', '');
        file_put_contents($this->root . '/c.txt', '');

        $paths = iterator_to_array(PhelFileIterator::iterate($this->root), false);

        self::assertCount(2, $paths);
        self::assertContains($this->root . '/a.phel', $paths);
        self::assertContains($this->root . '/nested/b.phel', $paths);
    }

    public function test_missing_directory_yields_empty_iterable(): void
    {
        $paths = iterator_to_array(PhelFileIterator::iterate('/does/not/exist/anywhere'), false);

        self::assertSame([], $paths);
    }

    public function test_directory_without_phel_files_yields_nothing(): void
    {
        self::assertDirectoryExists($this->root);

        $paths = iterator_to_array(PhelFileIterator::iterate($this->root), false);

        self::assertSame([], $paths);
    }
}
