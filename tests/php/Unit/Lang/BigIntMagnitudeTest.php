<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang;

use Phel\Lang\BigIntMagnitude;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BigIntMagnitudeTest extends TestCase
{
    /**
     * @return iterable<string, array{list<int>, list<int>}>
     */
    public static function magnitudes(): iterable
    {
        yield 'empty stays empty' => [[], []];
        yield 'all zeros trim to empty' => [[0, 0, 0], []];
        yield 'trailing zeros dropped' => [[5, 7, 0, 0], [5, 7]];
        yield 'inner zeros kept' => [[0, 3, 0, 9], [0, 3, 0, 9]];
    }

    /**
     * @param list<int> $magnitude
     * @param list<int> $expected
     */
    #[DataProvider('magnitudes')]
    public function test_trim(array $magnitude, array $expected): void
    {
        self::assertSame($expected, BigIntMagnitude::trim($magnitude));
    }
}
