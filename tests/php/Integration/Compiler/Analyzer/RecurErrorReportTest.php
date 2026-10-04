<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler\Analyzer;

use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompilerException;
use PhelTest\Integration\Compiler\AbstractCompilerRuntimeTestCase;
use PhelTest\Support\RendersExceptionReportTrait;

/**
 * Pins what a misplaced or mis-arity `recur` prints: PHEL010, the wording, and
 * the line and column the caret lands on.
 */
final class RecurErrorReportTest extends AbstractCompilerRuntimeTestCase
{
    use RendersExceptionReportTrait;

    private const string SOURCE = 'recur.phel';

    public function test_a_recur_outside_a_recur_point_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL010] Can't call 'recur here. See more: https://phel-lang.org/blog/loop-and-recur
            in recur.phel:1

            1| (recur 1)
                ^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(recur 1)'));
    }

    public function test_a_recur_with_the_wrong_arity_names_the_code(): void
    {
        $expected = <<<'REPORT'
            [PHEL010] Wrong number of arguments for 'recur. Expected: 1 args, got: 0
            in recur.phel:1

            1| (loop [x 1] (recur))
                           ^^^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(loop [x 1] (recur))'));
    }

    public function test_a_recur_in_a_foreach_body_cannot_target_the_enclosing_loop(): void
    {
        $expected = <<<'REPORT'
            [PHEL010] Can't call 'recur here. See more: https://phel-lang.org/blog/loop-and-recur
            in recur.phel:1

            1| (loop [i 0] (foreach [x [1 2]] (recur 10)))
                                               ^^^^^

            REPORT;

        self::assertSame($expected, $this->report('(loop [i 0] (foreach [x [1 2]] (recur 10)))'));
    }

    public function test_a_recur_in_a_doseq_body_cannot_target_the_enclosing_fn(): void
    {
        $report = $this->report('(fn [i] (doseq [x [1 2]] (when (< i 1) (recur 10))))');

        self::assertStringStartsWith("[PHEL010] Can't call 'recur here.", $report);
    }

    public function test_a_loop_inside_a_foreach_body_keeps_its_own_recur(): void
    {
        $result = $this->compilerFacade->compile(
            '(foreach [x [1 2]] (loop [i 0] (if (< i x) (recur (inc i)) i)))',
            new CompileOptions()->setSource(self::SOURCE),
        );

        self::assertStringContainsString('foreach', $result->getPhpCode());
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
