<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use InvalidArgumentException;
use Phel\Shared\OptimizationLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function putenv;

final class OptimizationLevelTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(OptimizationLevel::PIN_ENV);
    }

    public function test_the_configured_level_applies_without_a_pin(): void
    {
        self::assertSame(2, OptimizationLevel::resolve(2));
        self::assertSame(0, OptimizationLevel::resolve(-1));
        self::assertSame(0, OptimizationLevel::resolve('not a level'));
    }

    public function test_a_pin_wins_over_the_configured_level(): void
    {
        OptimizationLevel::pin(0);

        self::assertSame(0, OptimizationLevel::resolve(2));
    }

    public function test_the_environment_sets_the_level(): void
    {
        putenv(OptimizationLevel::PIN_ENV . '=2');

        self::assertSame(2, OptimizationLevel::resolve(0));
    }

    public function test_an_empty_value_counts_as_unset(): void
    {
        putenv(OptimizationLevel::PIN_ENV . '=');

        self::assertSame(2, OptimizationLevel::resolve(2));
    }

    #[DataProvider('provideInvalidLevels')]
    public function test_an_invalid_value_is_rejected_by_name(string $value): void
    {
        putenv(OptimizationLevel::PIN_ENV . '=' . $value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PHEL_OPTIMIZATION_LEVEL must be a non-negative integer such as 0 or 2, got "' . $value . '".');

        OptimizationLevel::resolve(0);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidLevels(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'word' => ['fast'];
        yield 'decimal' => ['1.5'];
        yield 'padded' => [' 2'];
    }
}
