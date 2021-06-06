<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Repl;

use Phel\Command\Repl\InputResult;
use PHPUnit\Framework\TestCase;

final class InputResultTest extends TestCase
{
    public function test_empty(): void
    {
        $result = InputResult::empty();
        $actual = $result->readBuffer(['_']);
        self::assertSame('_', $actual);
    }

    public function test_buffer_boolean(): void
    {
        $result = InputResult::fromEval(true);
        $actual = $result->readBuffer(['_']);
        self::assertSame('true', $actual);
    }

    public function test_buffer_null(): void
    {
        $result = InputResult::fromEval(null);
        $actual = $result->readBuffer(['_']);
        self::assertSame('nil', $actual);
    }

    public function test_buffer_numerical(): void
    {
        $result = InputResult::fromEval(2.3);
        $actual = $result->readBuffer(['(+ 1 _)']);
        self::assertSame('(+ 1 2.3)', $actual);
    }

    public function test_buffer_string(): void
    {
        $result = InputResult::fromEval('hello');
        $actual = $result->readBuffer(['(concat _ _)']);
        self::assertSame('(concat "hello" "hello")', $actual);
    }

    public function test_buffer_multiline(): void
    {
        $result = InputResult::fromEval('str');
        $actual = $result->readBuffer(['(concat', '_', '_)']);
        $expected = <<<TXT
(concat
"str"
"str")
TXT;
        self::assertSame($expected, $actual);
    }
}
