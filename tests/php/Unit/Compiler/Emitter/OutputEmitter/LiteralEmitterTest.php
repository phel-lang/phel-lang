<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Emitter\OutputEmitter;

use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Emitter\OutputEmitter\LiteralEmitter;
use Phel\Lang\BigInteger;
use Phel\Lang\Rational;
use Phel\Shared\Printer\Printer;
use PHPUnit\Framework\TestCase;

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
        $this->literalEmitter->emitLiteral(BigInteger::fromString('123456789012345678901234567890'));

        $this->expectOutputString('\\' . BigInteger::class . '::fromString("123456789012345678901234567890")');
    }

    public function test_emit_negative_big_integer(): void
    {
        $this->literalEmitter->emitLiteral(BigInteger::fromString('-99999999999999999999'));

        $this->expectOutputString('\\' . BigInteger::class . '::fromString("-99999999999999999999")');
    }

    public function test_emit_rational_uses_create_factory_with_big_integer_arguments(): void
    {
        $rational = Rational::create(BigInteger::fromString('1'), BigInteger::fromString('2'));
        self::assertInstanceOf(Rational::class, $rational);

        $this->literalEmitter->emitLiteral($rational);

        $this->expectOutputString(
            '\\' . Rational::class . '::create('
            . '\\' . BigInteger::class . '::fromString("1"), '
            . '\\' . BigInteger::class . '::fromString("2")'
            . ')',
        );
    }

    public function test_emit_negative_rational(): void
    {
        $rational = Rational::create(BigInteger::fromString('-3'), BigInteger::fromString('4'));
        self::assertInstanceOf(Rational::class, $rational);

        $this->literalEmitter->emitLiteral($rational);

        $this->expectOutputString(
            '\\' . Rational::class . '::create('
            . '\\' . BigInteger::class . '::fromString("-3"), '
            . '\\' . BigInteger::class . '::fromString("4")'
            . ')',
        );
    }

    public function test_emit_integer_valued_float_appends_decimal(): void
    {
        $this->literalEmitter->emitLiteral(10.0);

        $this->expectOutputString('10.0');
    }

    public function test_emit_scientific_float_does_not_append_decimal(): void
    {
        $this->literalEmitter->emitLiteral(-9.223372036854776E+18);

        $this->expectOutputString('-9.2233720368548E+18');
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
