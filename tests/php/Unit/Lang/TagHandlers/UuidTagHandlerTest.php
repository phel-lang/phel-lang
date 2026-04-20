<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\TagHandlers;

use Phel\Lang\TagHandlerException;
use Phel\Lang\TagHandlers\UuidTagHandler;
use PHPUnit\Framework\TestCase;

final class UuidTagHandlerTest extends TestCase
{
    private UuidTagHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new UuidTagHandler();
    }

    public function test_it_returns_lower_cased_uuid(): void
    {
        self::assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            ($this->handler)('550E8400-E29B-41D4-A716-446655440000'),
        );
    }

    public function test_it_rejects_invalid_format(): void
    {
        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('is not a canonical UUID string');

        ($this->handler)('not-a-uuid');
    }

    public function test_it_rejects_non_string(): void
    {
        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('#uuid expects a string literal');

        ($this->handler)(42);
    }
}
