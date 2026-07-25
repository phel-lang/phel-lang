<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\ErrorCode;

final class DefnMalformedNameRuntimeTest extends AbstractCompilerRuntimeTestCase
{
    public function test_defn_with_non_symbol_name_throws_clean_error(): void
    {
        // A non-symbol name used to reach `(getMeta)` in defn-builder and crash
        // with an internal PHP `Error` ("Call to a member function getMeta() on
        // int") that leaked core internals and a compiled-cache path. It must now
        // surface as a clean macro-expansion error naming the offending type.
        $this->assertMalformedNameError('(defn 123 [x] x)', 'must be a symbol, but got: int');
    }

    public function test_defmacro_with_non_symbol_name_throws_clean_error(): void
    {
        // defmacro routes through the same defn-builder, so it is guarded too.
        $this->assertMalformedNameError('(defmacro "nope" [x] x)', 'must be a symbol, but got: string');
    }

    public function test_private_defn_variant_with_non_symbol_name_throws_clean_error(): void
    {
        // `defn-` is claimed in the CHANGELOG; it inherits the guard via defn-builder.
        $this->assertMalformedNameError('(defn- 123 [x] x)', 'must be a symbol, but got: int');
    }

    public function test_private_defmacro_variant_with_non_symbol_name_throws_clean_error(): void
    {
        // `defmacro-` is claimed in the CHANGELOG; it inherits the guard via defn-builder.
        $this->assertMalformedNameError('(defmacro- "nope" [x] x)', 'must be a symbol, but got: string');
    }

    public function test_valid_defn_still_compiles(): void
    {
        $result = $this->compilerFacade->eval('(defn square [x] (* x x)) (square 6)', new CompileOptions());

        self::assertSame(36, $result);
    }

    private function assertMalformedNameError(string $phelSource, string $expectedMessageFragment): void
    {
        try {
            $this->compilerFacade->eval($phelSource, new CompileOptions());
            self::fail('Expected a CompilerException for malformed name');
        } catch (CompilerException $compilerException) {
            self::assertStringContainsString($expectedMessageFragment, $compilerException->getMessage());
            // The CHANGELOG promises a clean [PHEL005] macro-expansion error.
            self::assertSame(ErrorCode::MACRO_EXPANSION_ERROR, $compilerException->getNestedException()->getErrorCode());
        }
    }
}
