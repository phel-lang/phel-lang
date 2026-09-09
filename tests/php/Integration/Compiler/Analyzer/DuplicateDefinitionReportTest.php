<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Command\Application\TextExceptionPrinter;
use Phel\Command\Domain\ErrorLogInterface;
use Phel\Command\Domain\Exceptions\ExceptionArgsPrinterInterface;
use Phel\Command\Domain\Exceptions\Extractor\FilePositionExtractorInterface;
use Phel\Shared\ColorStyleInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\MungeInterface;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;

/**
 * Pins what a redefinition actually prints: the error code, the wording, the
 * line and column the caret lands on, and the line naming the first definition.
 */
final class DuplicateDefinitionReportTest extends AbstractCompilerRuntimeTestCase
{
    private const string SOURCE = 'duplicate.phel';

    public function test_a_redefinition_names_the_code_the_caret_and_the_first_definition(): void
    {
        $expected = <<<'REPORT'
            [PHEL004] Symbol 'f' is already bound in namespace 'repro.b'
            in duplicate.phel:3

            3| (defn f [x y] x)
                     ^

              first defined at duplicate.phel:2

            REPORT;

        $phelCode = "(ns repro.b)\n(defn f [x] x)\n(defn f [x y] x)";

        self::assertSame($expected, $this->report($phelCode));
    }

    public function test_a_redefinition_of_a_def_reports_the_def_name(): void
    {
        $expected = <<<'REPORT'
            [PHEL004] Symbol 'answer' is already bound in namespace 'repro.c'
            in duplicate.phel:3

            3| (def answer 43)
                    ^^^^^^

              first defined at duplicate.phel:2

            REPORT;

        $phelCode = "(ns repro.c)\n(def answer 42)\n(def answer 43)";

        self::assertSame($expected, $this->report($phelCode));
    }

    private function report(string $phelCode): string
    {
        try {
            $this->compilerFacade->compile($phelCode, new CompileOptions()->setSource(self::SOURCE));
        } catch (CompilerException $compilerException) {
            return $this->exceptionPrinter()->getExceptionString(
                $compilerException->getNestedException(),
                $compilerException->getCodeSnippet(),
            );
        }

        self::fail('Expected the compiler to reject: ' . $phelCode);
    }

    private function exceptionPrinter(): TextExceptionPrinter
    {
        $colorStyle = $this->createStub(ColorStyleInterface::class);
        $colorStyle->method('blue')->willReturnCallback(static fn(string $msg): string => $msg);
        $colorStyle->method('red')->willReturnCallback(static fn(string $msg): string => $msg);

        return new TextExceptionPrinter(
            $this->createStub(ExceptionArgsPrinterInterface::class),
            $colorStyle,
            $this->createStub(MungeInterface::class),
            $this->createStub(FilePositionExtractorInterface::class),
            $this->createStub(ErrorLogInterface::class),
        );
    }
}
