<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * A method the protocol does not declare used to surface as `Cannot resolve
 * symbol 'P--nope--dispatch'`, the name of an internal dispatch var (#3537).
 */
final class ProtocolMethodReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'protocol-method.phel';

    public function test_a_reify_method_the_protocol_does_not_declare_names_the_method(): void
    {
        self::assertStringStartsWith(
            "[PHEL005] Error in expanding macro \"phel.core/reify\"\n"
            . "  Expanding: (reify P (nope [this] 1))\n"
            . '  Cause: reify: protocol P has no method nope',
            $this->report("(ns protocol-method.a)\n(defprotocol P (m [this]))\n(reify P (nope [this] 1))"),
        );
    }

    public function test_an_extend_type_method_the_protocol_does_not_declare_names_the_method(): void
    {
        self::assertStringStartsWith(
            "[PHEL005] Error in expanding macro \"phel.core/extend-type\"\n"
            . "  Expanding: (extend-type :int P (nope [this] 1))\n"
            . '  Cause: extend-type: protocol P has no method nope',
            $this->report("(ns protocol-method.b)\n(defprotocol P (m [this]))\n(extend-type :int P (nope [this] 1))"),
        );
    }

    private function report(string $phelCode): string
    {
        try {
            $this->compilerFacade->compile($phelCode, new CompileOptions()->setSource(self::SOURCE));
        } catch (CompilerException $compilerException) {
            return $this->exceptionReport(
                $compilerException->getNestedException(),
                $compilerException->getCodeSnippet(),
            );
        }

        self::fail('Expected the compiler to reject: ' . $phelCode);
    }
}
