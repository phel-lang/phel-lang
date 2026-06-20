<?php

declare(strict_types=1);

namespace PhelTest\Unit\Shared\Exceptions\Hint;

use ArgumentCountError;
use Phel\Shared\Exceptions\Hint\ArgumentCountHint;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ArgumentCountHintTest extends TestCase
{
    public function test_does_not_apply_to_other_throwables(): void
    {
        self::assertFalse(new ArgumentCountHint()->appliesTo(new RuntimeException('boom')));
    }

    public function test_applies_to_argument_count_error(): void
    {
        $e = new ArgumentCountError(
            'foo() expects 1 passed exactly 2 expected, called somewhere',
        );

        self::assertTrue(new ArgumentCountHint()->appliesTo($e));
    }

    public function test_extracts_arity_when_message_matches(): void
    {
        $e = new ArgumentCountError('Too few arguments to function user\\bar(), 1 passed and exactly 2 expected');

        self::assertSame('wrong arity: expected 2 arguments, got 1.', new ArgumentCountHint()->hint($e));
    }

    public function test_singular_argument_label(): void
    {
        $e = new ArgumentCountError('Too few arguments to function user\\bar(), 0 passed and exactly 1 expected');

        self::assertSame('wrong arity: expected 1 argument, got 0.', new ArgumentCountHint()->hint($e));
    }

    public function test_falls_back_when_message_unparseable(): void
    {
        $e = new ArgumentCountError('weird message');

        self::assertSame('wrong number of arguments passed.', new ArgumentCountHint()->hint($e));
    }
}
