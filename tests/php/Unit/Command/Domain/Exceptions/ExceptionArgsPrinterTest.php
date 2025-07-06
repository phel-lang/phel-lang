<?php

declare(strict_types=1);

namespace PhelTest\Unit\Command\Domain\Exceptions;

use Generator;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinter;
use Phel\Printer\PrinterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ExceptionArgsPrinterTest extends TestCase
{
    public function test_parse_args_as_string(): void
    {
        $argsPrinter = $this->createExceptionArgsPrinter();
        $actual = $argsPrinter->parseArgsAsString(['1', '2']);
        self::assertSame(' 1 2', $actual);
    }

    #[DataProvider('providerBuildPhpArgsString')]
    public function test_build_php_args_string(array $args, string $expected): void
    {
        $argsPrinter = $this->createExceptionArgsPrinter();
        $actual = $argsPrinter->buildPhpArgsString($args);
        self::assertSame($expected, $actual);
    }

    public static function providerBuildPhpArgsString(): Generator
    {
        yield 'null' => [
            [null],
            'NULL',
        ];

        yield 'short string' => [
            ['short string'],
            "'short string'",
        ];

        yield 'long string' => [
            ['example long string'],
            "'example long st...'",
        ];

        yield 'booleans' => [
            [true, false],
            'true, false',
        ];

        yield 'array' => [
            [[1,2,3]],
            'Array',
        ];

        yield 'object' => [
            [new stdClass()],
            'Object(stdClass)',
        ];

        $resource = tmpfile();
        yield 'resource' => [
            [$resource],
            (string)$resource,
        ];
    }

    private function createExceptionArgsPrinter(): ExceptionArgsPrinter
    {
        return new ExceptionArgsPrinter($this->stubPrinter());
    }

    private function stubPrinter(): PrinterInterface
    {
        $printer = $this->createMock(PrinterInterface::class);
        $printer->method('print')->willReturnCallback(static fn ($arg): string => $arg);

        return $printer;
    }
}
