<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\CallTypeExpectationResolver;
use PHPUnit\Framework\TestCase;

/**
 * Locks the membership semantics of the op classifiers after the
 * in_array -> isset lookup-map conversion: a hit returns true, a
 * miss (including a near-miss) returns false.
 */
final class CallTypeExpectationResolverTest extends TestCase
{
    private CallTypeExpectationResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CallTypeExpectationResolver();
    }

    public function test_is_numeric_op(): void
    {
        foreach (['+', '-', '*', '/', '%', '**', '<<', '>>', '|', '&', '^'] as $op) {
            self::assertTrue($this->resolver->isNumericOp($op), $op);
        }

        self::assertFalse($this->resolver->isNumericOp('==='));
        self::assertFalse($this->resolver->isNumericOp('++'));
        self::assertFalse($this->resolver->isNumericOp(''));
    }

    public function test_is_identity_op(): void
    {
        foreach (['===', '!==', '==', '!='] as $op) {
            self::assertTrue($this->resolver->isIdentityOp($op), $op);
        }

        self::assertFalse($this->resolver->isIdentityOp('<'));
        self::assertFalse($this->resolver->isIdentityOp('='));
    }

    public function test_is_ordering_op(): void
    {
        foreach (['<', '>', '<=', '>=', '<=>'] as $op) {
            self::assertTrue($this->resolver->isOrderingOp($op), $op);
        }

        self::assertFalse($this->resolver->isOrderingOp('=='));
        self::assertFalse($this->resolver->isOrderingOp('!'));
    }

    public function test_is_guard_php_fn(): void
    {
        self::assertTrue($this->resolver->isGuardPhpFn('is_int'));
        self::assertTrue($this->resolver->isGuardPhpFn('is_scalar'));
        self::assertFalse($this->resolver->isGuardPhpFn('is_resource'));
        self::assertFalse($this->resolver->isGuardPhpFn('strlen'));
    }

    public function test_is_string_concat_op(): void
    {
        self::assertTrue($this->resolver->isStringConcatOp('.'));
        self::assertFalse($this->resolver->isStringConcatOp('+'));
    }
}
