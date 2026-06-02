<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Analyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm\CallTypeExpectationResolver;
use PHPUnit\Framework\TestCase;

final class CallTypeExpectationResolverTest extends TestCase
{
    private CallTypeExpectationResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new CallTypeExpectationResolver();
    }

    public function test_numeric_ops_are_classified(): void
    {
        self::assertTrue($this->resolver->isNumericOp('+'));
        self::assertTrue($this->resolver->isNumericOp('<<'));
        self::assertFalse($this->resolver->isNumericOp('<'));
        self::assertFalse($this->resolver->isNumericOp('.'));
    }

    public function test_string_concat_op_is_classified(): void
    {
        self::assertTrue($this->resolver->isStringConcatOp('.'));
        self::assertFalse($this->resolver->isStringConcatOp('+'));
    }

    public function test_identity_and_ordering_ops_are_disjoint(): void
    {
        self::assertTrue($this->resolver->isIdentityOp('==='));
        self::assertFalse($this->resolver->isOrderingOp('==='));

        self::assertTrue($this->resolver->isOrderingOp('<='));
        self::assertFalse($this->resolver->isIdentityOp('<='));
    }

    public function test_guard_php_fns_are_recognised(): void
    {
        self::assertTrue($this->resolver->isGuardPhpFn('is_int'));
        self::assertTrue($this->resolver->isGuardPhpFn('is_string'));
        self::assertFalse($this->resolver->isGuardPhpFn('strlen'));
    }

    public function test_php_fn_signature_returns_per_slot_tags_or_null(): void
    {
        self::assertSame(['int', 'int'], $this->resolver->phpFnSignature('random_int'));
        self::assertSame(['string', 'int'], $this->resolver->phpFnSignature('str_repeat'));
        self::assertNull($this->resolver->phpFnSignature('unlisted_fn'));
    }

    public function test_literal_numeric_type_is_null_without_literal_args(): void
    {
        self::assertNull($this->resolver->literalNumericType([]));
    }
}
