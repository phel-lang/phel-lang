<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\TagHandlers;

use Phel\Lang\TagHandlerException;
use Phel\Lang\TagHandlers\UUIDTagHandler;
use Phel\Lang\UUID;
use PHPUnit\Framework\TestCase;

final class UUIDTagHandlerTest extends TestCase
{
    private UUIDTagHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new UUIDTagHandler();
    }

    public function test_it_returns_lower_cased_uuid_value(): void
    {
        $result = ($this->handler)('550E8400-E29B-41D4-A716-446655440000');

        self::assertInstanceOf(UUID::class, $result);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $result);
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
