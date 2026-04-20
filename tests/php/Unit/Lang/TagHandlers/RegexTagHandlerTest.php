<?php

declare(strict_types=1);

namespace PhelTest\Unit\Lang\TagHandlers;

use Phel\Lang\TagHandlerException;
use Phel\Lang\TagHandlers\RegexTagHandler;
use PHPUnit\Framework\TestCase;

final class RegexTagHandlerTest extends TestCase
{
    private RegexTagHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new RegexTagHandler();
    }

    public function test_it_wraps_pattern_in_slashes(): void
    {
        self::assertSame('/[a-z]+/', ($this->handler)('[a-z]+'));
    }

    public function test_it_escapes_unescaped_forward_slash(): void
    {
        self::assertSame('/a\\/b/', ($this->handler)('a/b'));
    }

    public function test_it_leaves_escaped_forward_slash_alone(): void
    {
        self::assertSame('/a\\/b/', ($this->handler)('a\\/b'));
    }

    public function test_it_rejects_non_string(): void
    {
        $this->expectException(TagHandlerException::class);
        $this->expectExceptionMessage('#regex expects a string literal');

        ($this->handler)(1);
    }
}
