<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\Generators;

use InvalidArgumentException;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Generators\FileGenerator;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

final class FileGeneratorTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'phel-file-generator-') ?: '';
        self::assertNotSame('', $this->file);
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function test_file_lines_strips_line_endings(): void
    {
        file_put_contents($this->file, "one\r\ntwo\nthree");

        self::assertSame(
            ['one', 'two', 'three'],
            iterator_to_array(FileGenerator::fileLines($this->file), false),
        );
    }

    public function test_file_lines_rejects_missing_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument filename should be a valid path to a file: ');

        iterator_to_array(FileGenerator::fileLines($this->file . '-missing'), false);
    }

    public function test_read_file_chunks_yields_non_empty_chunks(): void
    {
        file_put_contents($this->file, 'abcdef');

        self::assertSame(
            ['abc', 'def'],
            iterator_to_array(FileGenerator::readFileChunks($this->file, 3), false),
        );
    }

    public function test_read_file_chunks_rejects_non_positive_chunk_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk size must be positive, got: 0');

        iterator_to_array(FileGenerator::readFileChunks($this->file, 0), false);
    }

    public function test_read_file_chunks_rejects_missing_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument filename should be a valid path to a file: ');

        iterator_to_array(FileGenerator::readFileChunks($this->file . '-missing'), false);
    }

    public function test_csv_lines_yields_vectors(): void
    {
        file_put_contents($this->file, "a,b\nc,d\n");

        $rows = iterator_to_array(FileGenerator::csvLines($this->file), false);

        self::assertCount(2, $rows);
        self::assertInstanceOf(PersistentVectorInterface::class, $rows[0]);
        self::assertSame(['a', 'b'], iterator_to_array($rows[0], false));
        self::assertInstanceOf(PersistentVectorInterface::class, $rows[1]);
        self::assertSame(['c', 'd'], iterator_to_array($rows[1], false));
    }

    public function test_csv_lines_rejects_missing_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument filename should be a valid path to a file: ');

        iterator_to_array(FileGenerator::csvLines($this->file . '-missing'), false);
    }

    public function test_file_seq_yields_the_file_itself(): void
    {
        self::assertSame([$this->file], iterator_to_array(FileGenerator::fileSeq($this->file), false));
    }

    public function test_file_seq_rejects_missing_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path does not exist: ');

        iterator_to_array(FileGenerator::fileSeq($this->file . '-missing'), false);
    }
}
