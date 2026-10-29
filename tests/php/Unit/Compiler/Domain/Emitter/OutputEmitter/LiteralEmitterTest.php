<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Emitter\OutputEmitter\LiteralEmitter;
use Phel\Lang\BigInt;
use Phel\Lang\Ratio;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function ini_get;

final class LiteralEmitterTest extends TestCase
{
    private LiteralEmitter $literalEmitter;

    protected function setUp(): void
    {
        $outputEmitter = new CompilerFactory()
            ->createOutputEmitter(false);

        $this->literalEmitter = new LiteralEmitter(
            $outputEmitter,
            Printer::readable(),
        );
    }

    public function test_emit_big_integer_uses_from_string_factory(): void
    {
        $this->literalEmitter->emitLiteral(BigInt::fromString('123456789012345678901234567890'));

        $this->expectOutputString('\\' . BigInt::class . '::fromString("123456789012345678901234567890")');
    }

    public function test_emit_negative_big_integer(): void
    {
        $this->literalEmitter->emitLiteral(BigInt::fromString('-99999999999999999999'));

        $this->expectOutputString('\\' . BigInt::class . '::fromString("-99999999999999999999")');
    }

    public function test_emit_rational_uses_create_factory_with_big_integer_arguments(): void
    {
        $rational = Ratio::create(BigInt::fromString('1'), BigInt::fromString('2'));
        self::assertInstanceOf(Ratio::class, $rational);

        $this->literalEmitter->emitLiteral($rational);

        $this->expectOutputString(
            '\\' . Ratio::class . '::create('
            . '\\' . BigInt::class . '::fromString("1"), '
            . '\\' . BigInt::class . '::fromString("2")'
            . ')',
        );
    }

    public function test_emit_negative_rational(): void
    {
        $rational = Ratio::create(BigInt::fromString('-3'), BigInt::fromString('4'));
        self::assertInstanceOf(Ratio::class, $rational);

        $this->literalEmitter->emitLiteral($rational);

        $this->expectOutputString(
            '\\' . Ratio::class . '::create('
            . '\\' . BigInt::class . '::fromString("-3"), '
            . '\\' . BigInt::class . '::fromString("4")'
            . ')',
        );
    }

    /**
     * @return iterable<string, array{float, int, int}>
     */
    public static function exactFloats(): iterable
    {
        foreach ([14, 3] as $precision) {
            foreach ([-1, 3] as $serializePrecision) {
                foreach ([0.30000000000000004, PHP_FLOAT_MAX, PHP_FLOAT_MIN, 5.0e-324, -0.0, 10.0, 1.0e100] as $index => $value) {
                    yield $precision . ':' . $serializePrecision . ':' . $index => [$value, $precision, $serializePrecision];
                }
            }
        }
    }

    #[DataProvider('exactFloats')]
    public function test_emitted_float_preserves_every_bit(float $value, int $precision, int $serializePrecision): void
    {
        $oldPrecision = ini_set('precision', (string) $precision);
        $oldSerializePrecision = ini_set('serialize_precision', (string) $serializePrecision);
        ob_start();
        try {
            $this->literalEmitter->emitLiteral($value);
            $code = ob_get_contents();
            $actual = eval('return ' . $code . ';');
            self::assertSame(pack('d', $value), pack('d', $actual));
            self::assertIsFloat($actual);
            self::assertSame((string) $precision, ini_get('precision'));
            self::assertSame((string) $serializePrecision, ini_get('serialize_precision'));
        } finally {
            ob_end_clean();
            ini_set('precision', $oldPrecision);
            ini_set('serialize_precision', $oldSerializePrecision);
        }
    }

    public function test_emit_integer_valued_float_appends_decimal(): void
    {
        $this->literalEmitter->emitLiteral(10.0);

        $this->expectOutputString('10.0');
    }

    public function test_emit_scientific_float_does_not_append_decimal(): void
    {
        $this->literalEmitter->emitLiteral(-9.223372036854776E+18);

        $this->expectOutputString('-9.223372036854776E+18');
    }

    public function test_emit_nan_uses_constant(): void
    {
        $this->literalEmitter->emitLiteral(NAN);

        $this->expectOutputString('NAN');
    }

    public function test_emit_positive_infinity_uses_constant(): void
    {
        $this->literalEmitter->emitLiteral(INF);

        $this->expectOutputString('INF');
    }

    public function test_emit_negative_infinity_uses_constant(): void
    {
        $this->literalEmitter->emitLiteral(-INF);

        $this->expectOutputString('-INF');
    }
}
