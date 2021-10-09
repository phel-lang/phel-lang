<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Repl;

use Phel\Run\Domain\Repl\InputResult;
use PHPUnit\Framework\TestCase;

final class InputResultTest extends TestCase
{
    public function test_empty(): void
    {
        $result = InputResult::empty();
        $actual = $result->readBuffer(['$_']);

        self::assertSame('$_', $actual);
    }

    public function test_buffer_boolean(): void
    {
        $result = InputResult::fromAny(true);
        $actual = $result->readBuffer(['$_']);

        self::assertSame('true', $actual);
    }

    public function test_buffer_null(): void
    {
        $result = InputResult::fromAny(null);
        $actual = $result->readBuffer(['$_']);

        self::assertSame('nil', $actual);
    }

    public function test_buffer_numerical(): void
    {
        $result = InputResult::fromAny(2.3);
        $actual = $result->readBuffer(['(+ 1 $_)']);

        self::assertSame('(+ 1 2.3)', $actual);
    }

    public function test_buffer_string(): void
    {
        $result = InputResult::fromAny('hello');
        $actual = $result->readBuffer(['(concat $_ $_)']);

        self::assertSame('(concat "hello" "hello")', $actual);
    }

    public function test_buffer_multiline(): void
    {
        $result = InputResult::fromAny('str');
        $actual = $result->readBuffer(['(concat', '$_', '$_)']);
        $expected = <<<TXT
(concat
"str"
"str")
TXT;
        self::assertSame($expected, $actual);
    }

    public function test_buffer_with_normal_underscore(): void
    {
        $result = InputResult::fromAny(true);
        $actual = $result->readBuffer(['(let [a $_ _ 20] a)']);

        self::assertSame('(let [a true _ 20] a)', $actual);
    }

    public function test_buffer_inside_string(): void
    {
        $result = InputResult::fromAny(1);
        $actual = $result->readBuffer(['"$_" $_']);

        self::assertSame('"$_" 1', $actual);
    }
}
