<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\TagHandlers;

use DateTimeImmutable;
use Phel\Lang\TagHandlerException;
use Phel\Lang\TagHandlers\InstTagHandler;
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

    public function test_it_rejects_date_only_value(): void
    {
        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('is not a valid ISO 8601');

        ($this->handler)('2026-04-20');
    }
}
