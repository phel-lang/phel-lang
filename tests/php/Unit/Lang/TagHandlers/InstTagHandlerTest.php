<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\TagHandlers;

use DateTimeImmutable;
use Phel\Lang\TagHandlerException;
use Phel\Lang\TagHandlers\InstTagHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InstTagHandlerTest extends TestCase
{
    private InstTagHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new InstTagHandler();
    }

    public function test_it_parses_utc_timestamp(): void
    {
        $result = ($this->handler)('2026-04-20T12:00:00Z');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2026-04-20T12:00:00+00:00', $result->format(DATE_ATOM));
    }

    public function test_it_parses_timestamp_with_offset(): void
    {
        $result = ($this->handler)('2026-04-20T12:00:00+02:00');

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2026-04-20T12:00:00+02:00', $result->format(DATE_ATOM));
    }

    public function test_it_defaults_missing_offset_to_utc(): void
    {
        $result = ($this->handler)('2026-04-20T12:00:00');

        self::assertSame('2026-04-20T12:00:00+00:00', $result->format(DATE_ATOM));
    }

    public function test_it_accepts_fractional_seconds(): void
    {
        $result = ($this->handler)('2026-04-20T12:00:00.123Z');

        self::assertSame('2026-04-20T12:00:00+00:00', $result->format(DATE_ATOM));
    }

    public function test_it_rejects_non_string(): void
    {
        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('#inst expects a string literal');

        ($this->handler)(42);
    }

    public function test_it_rejects_invalid_format(): void
    {
        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('is not a valid ISO 8601');

        ($this->handler)('bad-date');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePartialTimestamps(): iterable
    {
        yield 'year' => ['2026', '2026-01-01T00:00:00+00:00'];
        yield 'month' => ['2026-03', '2026-03-01T00:00:00+00:00'];
        yield 'day' => ['2026-03-04', '2026-03-04T00:00:00+00:00'];
        yield 'hour' => ['2026-03-04T05', '2026-03-04T05:00:00+00:00'];
        yield 'minute' => ['2026-03-04T05:06', '2026-03-04T05:06:00+00:00'];
        yield 'year with Z' => ['2026Z', '2026-01-01T00:00:00+00:00'];
        yield 'day with offset' => ['2026-03-04+02:00', '2026-03-04T00:00:00+02:00'];
        yield 'hour with negative offset' => ['2026-03-04T05-0530', '2026-03-04T05:00:00-05:30'];
        yield 'leap day' => ['2024-02-29', '2024-02-29T00:00:00+00:00'];
    }

    #[DataProvider('providePartialTimestamps')]
    public function test_it_accepts_every_prefix(string $literal, string $expected): void
    {
        $result = ($this->handler)($literal);

        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame($expected, $result->format(DATE_ATOM));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidTimestamps(): iterable
    {
        yield 'february 30' => ['2026-02-30'];
        yield 'not a leap year' => ['2026-02-29'];
        yield 'month 13' => ['2026-13'];
        yield 'month 00' => ['2026-00'];
        yield 'day 00' => ['2026-01-00'];
        yield 'hour 25' => ['2026-01-01T25'];
        yield 'minute 60' => ['2026-01-01T05:60'];
        yield 'offset hour 24' => ['2026-01-01+24:00'];
        yield 'two digit year' => ['26'];
        yield 'dangling dash' => ['2026-'];
        yield 'time without date' => ['T05'];
    }

    #[DataProvider('provideInvalidTimestamps')]
    public function test_it_rejects_invalid_fields(string $literal): void
    {
        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('is not a valid ISO 8601');

        ($this->handler)($literal);
    }
}
