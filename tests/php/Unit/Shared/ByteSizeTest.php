<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use Phel\Shared\ByteSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ByteSizeTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideSizes(): iterable
    {
        yield 'zero' => [0, '0 B'];
        yield 'one byte below a kilobyte' => [1023, '1023 B'];
        yield 'exactly a kilobyte' => [1024, '1.00 KB'];
        yield 'one byte below a megabyte' => [1_048_575, '1024.00 KB'];
        yield 'exactly a megabyte' => [1_048_576, '1.00 MB'];
        yield 'rounds to two decimals' => [1_572_864, '1.50 MB'];
        yield 'one byte below a gigabyte' => [1_073_741_823, '1024.00 MB'];
        yield 'exactly a gigabyte' => [1_073_741_824, '1.00 GB'];
    }

    #[DataProvider('provideSizes')]
    public function test_formats_the_largest_fitting_unit(int $bytes, string $expected): void
    {
        self::assertSame($expected, ByteSize::format($bytes));
    }
}
