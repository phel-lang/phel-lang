<?php

declare(strict_types=1);

namespace PhelTest\Unit\Printer\TypePrinter;

use Generator;
use Phel\Compiler\CompilerFactory;
use Phel\Shared\Printer\TypePrinter\NumberPrinter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function count;
use function ini_get;

final class NumberPrinterTest extends TestCase
{
    #[DataProvider('printerDataProvider')]
    public function test_print(string $expected, float|int $number): void
    {
        self::assertSame(
            $expected,
            new NumberPrinter()->print($number),
        );
    }

    public static function printerDataProvider(): Generator
    {
        yield 'integer' => [
            '1',
            1,
        ];

        yield 'float' => [
            '1.02',
            1.02,
        ];

        yield 'integer-valued float keeps .0' => [
            '17.0',
            17.0,
        ];

        yield 'nan' => [
            'NaN',
            NAN,
        ];

        yield 'positive infinity' => [
            'Infinity',
            INF,
        ];

        yield 'negative infinity' => [
            '-Infinity',
            -INF,
        ];
    }

    public function test_finite_floats_round_trip_through_the_reader_without_precision_loss(): void
    {
        $compiler = new CompilerFactory();
        $lexer = $compiler->createLexer();
        $parser = $compiler->createParser();
        $reader = $compiler->createReader();
        $printer = new NumberPrinter();
        $values = [1.0 / 3, 1.0000000000000002, PHP_FLOAT_MAX, PHP_FLOAT_MIN, 5.0e-324, -0.0, 17.0];
        for ($index = 0; $index < 512; ++$index) {
            $value = unpack('evalue', substr(hash('sha256', 'phel-float-' . $index, true), 0, 8))['value'];
            if (is_finite($value)) {
                $values[] = $value;
            }
        }

        self::assertGreaterThan(500, count($values));
        foreach ([[14, -1], [3, 3]] as [$precision, $serializePrecision]) {
            $oldPrecision = ini_set('precision', (string) $precision);
            $oldSerializePrecision = ini_set('serialize_precision', (string) $serializePrecision);
            try {
                foreach ($values as $value) {
                    $printed = $printer->print($value);
                    $node = $parser->parseNext($lexer->lexString($printed));
                    self::assertNotNull($node);
                    $parsed = $reader->read($node)->getAst();
                    self::assertIsFloat($parsed);
                    self::assertSame(pack('d', $value), pack('d', $parsed), $printed);
                    self::assertSame((string) $precision, ini_get('precision'));
                    self::assertSame((string) $serializePrecision, ini_get('serialize_precision'));
                }
            } finally {
                ini_set('precision', $oldPrecision);
                ini_set('serialize_precision', $oldSerializePrecision);
            }
        }
    }

    public function test_print_nan_does_not_emit_a_php_warning(): void
    {
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new RuntimeException($errstr);
        });

        try {
            self::assertSame('NaN', new NumberPrinter()->print(NAN));
        } finally {
            restore_error_handler();
        }
    }
}
