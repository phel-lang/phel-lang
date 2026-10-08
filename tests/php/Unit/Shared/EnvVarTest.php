<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared;

use InvalidArgumentException;
use Phel\Shared\EnvVar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function putenv;

final class EnvVarTest extends TestCase
{
    private const string NAME = 'PHEL_ENV_VAR_TEST';

    protected function tearDown(): void
    {
        putenv(self::NAME);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideFlagSpellings(): iterable
    {
        foreach (['1', 'true', 'yes', 'on', 'TRUE', 'Yes', 'oN', ' on ', "true\n"] as $value) {
            yield 'on: ' . $value => [$value, true];
        }

        foreach (['0', 'false', 'no', 'off', 'FALSE', 'No', 'oFF', ' 0 ', "\tfalse"] as $value) {
            yield 'off: ' . $value => [$value, false];
        }
    }

    #[DataProvider('provideFlagSpellings')]
    public function test_a_flag_accepts_each_spelling_in_any_case(string $value, bool $expected): void
    {
        putenv(self::NAME . '=' . $value);

        self::assertSame($expected, EnvVar::flag(self::NAME));
    }

    #[DataProvider('provideUnsetValues')]
    public function test_an_unset_or_empty_flag_is_not_set(?string $value): void
    {
        $this->set($value);

        self::assertNull(EnvVar::flag(self::NAME));
        self::assertNull(EnvVar::integer(self::NAME, 0));
        self::assertFalse(EnvVar::isOn(self::NAME));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideUnsetValues(): iterable
    {
        yield 'unset' => [null];
        yield 'empty' => [''];
        yield 'blank' => ['  '];
    }

    #[DataProvider('provideBadFlags')]
    public function test_a_flag_rejects_any_other_value_by_name(string $value): void
    {
        putenv(self::NAME . '=' . $value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'PHEL_ENV_VAR_TEST must be one of 1, true, yes, on, 0, false, no or off, got "' . $value . '".',
        );

        EnvVar::flag(self::NAME);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBadFlags(): iterable
    {
        yield 'word' => ['maybe'];
        yield 'number' => ['2'];
        yield 'letter' => ['y'];
    }

    #[DataProvider('provideIntegers')]
    public function test_an_integer_is_read_with_surrounding_whitespace_ignored(string $value, int $expected): void
    {
        putenv(self::NAME . '=' . $value);

        self::assertSame($expected, EnvVar::integer(self::NAME, 1));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideIntegers(): iterable
    {
        yield 'plain' => ['3', 3];
        yield 'padded' => [' 12 ', 12];
        yield 'leading zero' => ['04', 4];
    }

    #[DataProvider('provideBadIntegers')]
    public function test_an_integer_rejects_any_other_value_by_name(string $value): void
    {
        putenv(self::NAME . '=' . $value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PHEL_ENV_VAR_TEST must be a whole number of at least 1, got "' . $value . '".');

        EnvVar::integer(self::NAME, 1);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBadIntegers(): iterable
    {
        yield 'word' => ['abc'];
        yield 'below the minimum' => ['0'];
        yield 'negative' => ['-4'];
        yield 'decimal' => ['1.5'];
        yield 'signed' => ['+2'];
    }

    #[DataProvider('provideIsOn')]
    public function test_is_on_treats_only_false_spellings_as_off_and_never_throws(string $value, bool $expected): void
    {
        putenv(self::NAME . '=' . $value);

        self::assertSame($expected, EnvVar::isOn(self::NAME));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideIsOn(): iterable
    {
        yield 'true' => ['true', true];
        yield 'a tool name' => ['woodpecker', true];
        yield 'false' => ['FALSE', false];
        yield 'zero' => ['0', false];
        yield 'off' => [' off ', false];
    }

    private function set(?string $value): void
    {
        putenv($value === null ? self::NAME : self::NAME . '=' . $value);
    }
}
