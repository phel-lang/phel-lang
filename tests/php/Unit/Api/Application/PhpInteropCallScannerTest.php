<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application;

use Phel\Api\Application\PhpInteropCallScanner;
use Phel\Api\Transfer\PhpInteropCall;
use PHPUnit\Framework\TestCase;

final class PhpInteropCallScannerTest extends TestCase
{
    private PhpInteropCallScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new PhpInteropCallScanner();
    }

    public function test_constructor_call_reports_class_and_first_parameter(): void
    {
        $call = $this->scanner->scan('(php/new \\DateTimeImmutable ');

        self::assertSame(PhpInteropCall::KIND_CONSTRUCTOR, $call->kind);
        self::assertSame('\\DateTimeImmutable', $call->receiver);
        self::assertSame(0, $call->activeParameter);
    }

    public function test_constructor_active_parameter_advances_with_arguments(): void
    {
        $call = $this->scanner->scan('(php/new \\DateTimeImmutable "now" ');

        self::assertSame(PhpInteropCall::KIND_CONSTRUCTOR, $call->kind);
        self::assertSame(1, $call->activeParameter);
    }

    public function test_method_call_reports_receiver_and_method(): void
    {
        $call = $this->scanner->scan('(php/-> (php/new \\DateTimeImmutable) (setTimestamp ');

        self::assertSame(PhpInteropCall::KIND_METHOD, $call->kind);
        self::assertSame('(php/new \\DateTimeImmutable)', $call->receiver);
        self::assertSame('setTimestamp', $call->method);
        self::assertSame(0, $call->activeParameter);
    }

    public function test_method_active_parameter_counts_arguments(): void
    {
        $call = $this->scanner->scan('(php/-> obj (setDate 2020 1 ');

        self::assertSame('setDate', $call->method);
        self::assertSame(2, $call->activeParameter);
    }

    public function test_in_progress_argument_is_not_counted(): void
    {
        $call = $this->scanner->scan('(php/-> obj (setDate 2020 1');

        // Still typing the second argument: index 1, not 2.
        self::assertSame(1, $call->activeParameter);
    }

    public function test_chained_call_reports_the_innermost_method_not_the_first(): void
    {
        $call = $this->scanner->scan('(php/-> (php/new \\DateTimeImmutable) (modify "x") (setDate 2020 ');

        self::assertSame(PhpInteropCall::KIND_METHOD, $call->kind);
        self::assertSame('setDate', $call->method);
        self::assertSame(1, $call->activeParameter);
    }

    public function test_static_method_call_reports_class_receiver(): void
    {
        $call = $this->scanner->scan('(php/:: \\DateTimeImmutable (createFromFormat ');

        self::assertSame(PhpInteropCall::KIND_METHOD, $call->kind);
        self::assertSame('\\DateTimeImmutable', $call->receiver);
        self::assertSame('createFromFormat', $call->method);
    }

    public function test_nested_argument_form_counts_as_one(): void
    {
        $call = $this->scanner->scan('(php/-> obj (setDate (year) 1 ');

        self::assertSame('setDate', $call->method);
        self::assertSame(2, $call->activeParameter);
    }

    public function test_multiline_method_call_is_resolved(): void
    {
        $call = $this->scanner->scan("(php/-> (php/new \\DateTimeImmutable)\n  (setTimestamp ");

        self::assertSame(PhpInteropCall::KIND_METHOD, $call->kind);
        self::assertSame('setTimestamp', $call->method);
    }

    public function test_receiver_before_method_paren_is_not_a_call(): void
    {
        self::assertTrue($this->scanner->scan('(php/-> obj ')->isNone());
    }

    public function test_plain_phel_is_not_a_call(): void
    {
        self::assertTrue($this->scanner->scan('(map inc ')->isNone());
    }
}
