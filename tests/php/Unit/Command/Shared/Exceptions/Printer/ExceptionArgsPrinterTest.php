<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Shared\Exceptions\Printer;

use Generator;
use Phel\Command\Shared\Exceptions\ExceptionArgsPrinter;
use Phel\Printer\PrinterInterface;
use PHPUnit\Framework\TestCase;

final class ExceptionArgsPrinterTest extends TestCase
{
    public function test_parse_args_as_string(): void
    {
        $argsPrinter = $this->createExceptionArgsPrinter();
        $actual = $argsPrinter->parseArgsAsString(['1', '2']);
        self::assertEquals(' 1 2', $actual);
    }

    /**
     * @dataProvider providerBuildPhpArgsString
     */
    public function test_build_php_args_string(array $args, string $expected): void
    {
        $argsPrinter = $this->createExceptionArgsPrinter();
        $actual = $argsPrinter->buildPhpArgsString($args);
        self::assertEquals($expected, $actual);
    }

    public function providerBuildPhpArgsString(): Generator
    {
        yield 'null' => [
            'args' => [null],
            'expected' => 'NULL',
        ];

        yield 'short string' => [
            'args' => ['short string'],
            'expected' => "'short string'",
        ];

        yield 'long string' => [
            'args' => ['example long string'],
            'expected' => "'example long st...'",
        ];

        yield 'booleans' => [
            'args' => [true, false],
            'expected' => 'true, false',
        ];

        yield 'array' => [
            'args' => [[1,2,3]],
            'expected' => 'Array',
        ];

        yield 'object' => [
            'args' => [new \stdClass()],
            'expected' => 'Object(stdClass)',
        ];

        $resource = tmpfile();
        yield 'resource' => [
            'args' => [$resource],
            'expected' => (string)$resource,
        ];
    }

    private function createExceptionArgsPrinter(): ExceptionArgsPrinter
    {
        return new ExceptionArgsPrinter($this->stubPrinter());
    }

    private function stubPrinter(): PrinterInterface
    {
        $printer = $this->createMock(PrinterInterface::class);
        $printer->method('print')->willReturnCallback(fn ($arg) => $arg);

        return $printer;
    }
}
