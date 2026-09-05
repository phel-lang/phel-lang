<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\LoadClasspath;
use Phel\Lang\Symbol;
use Phel\Shared\CompileOptions;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

use function implode;
use function sprintf;

/**
 * What macro-heavy source costs to analyze and to compile.
 *
 * Almost every form a real program writes is a macro call: `when`, `cond`,
 * `->`, `if-let`, `for`, `case`. Expanding one walks the expansion to stamp
 * the call site onto the forms it synthesised, and that walk rebuilds every
 * list it passes. The existing compiler subjects do not reach it:
 * `WideLetAnalysisBench` analyzes one `let` with no macro in it,
 * `EmitterCaptureBench` drives the emitter alone, and `CallInliningBench` is
 * about `-O2`.
 *
 * Two subjects, because the two phases move independently:
 *
 * - `bench_analyze_macro_heavy` stops after analysis, so it isolates the
 *   expansion walk from anything the emitter does.
 * - `bench_compile_emit_only` runs the whole `phel compile` path, which emits
 *   without evaluating. That mode has to emit each form exactly once: its
 *   second emission existed only to feed an evaluator it then skipped.
 *
 * The fixture is built inline from a fixed shape, never read from `src/`, so
 * the subject measures compilation rather than the size of whatever file it
 * happened to point at.
 *
 * @BeforeMethods("setUp")
 */
final class MacroExpansionCompileBench
{
    private const int FORM_COUNT = 25;

    private const string FIXTURE_NS = 'phel.bench.macroexpansion';

    private CompilerFacade $compilerFacade;

    private string $fixtureSource = '';

    public function setUp(): void
    {
        $projectRoot = __DIR__ . '/../../../../';

        Phel::bootstrap($projectRoot);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
        LoadClasspath::publish([$projectRoot . 'src/phel']);

        new BuildFacade()->evalFile($projectRoot . 'src/phel/core.phel');
        BuildFacade::enableBuildMode();

        $this->compilerFacade = new CompilerFacade();
        $this->fixtureSource = $this->buildFixtureSource();
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function bench_analyze_macro_heavy(): void
    {
        $stream = $this->compilerFacade->lexString($this->fixtureSource, LexerInterface::DEFAULT_SOURCE);

        while (true) {
            $parseTree = $this->compilerFacade->parseNext($stream);
            if (!$parseTree instanceof NodeInterface) {
                break;
            }

            if ($parseTree instanceof TriviaNodeInterface) {
                continue;
            }

            $readerResult = $this->compilerFacade->read($parseTree);
            $this->compilerFacade->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()->withReturnContext(),
            );
        }
    }

    /**
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function bench_compile_emit_only(): void
    {
        $this->compilerFacade->compile(
            $this->fixtureSource,
            new CompileOptions()->setEmitOnly(true),
        );
    }

    /**
     * The whole path a `phel build` takes: compile and evaluate. Unlike
     * `bench_compile_emit_only` this reaches the evaluator, which is where a
     * form's emission is consumed, so it is the subject that sees whether the
     * second emission of each form is still being paid for.
     *
     * @Revs(20)
     *
     * @Iterations(5)
     */
    public function bench_compile_and_eval(): void
    {
        $this->compilerFacade->compile($this->fixtureSource, new CompileOptions());
    }

    /**
     * Builds `FORM_COUNT` functions whose bodies are nested macro calls:
     * threading, conditionals, binding conditionals and a comprehension, each
     * expanding into several synthesised lists. The shape is fixed, so the
     * only thing that varies between runs is the compiler.
     */
    private function buildFixtureSource(): string
    {
        $forms = [];
        for ($i = 0; $i < self::FORM_COUNT; ++$i) {
            $forms[] = sprintf(
                <<<'PHEL'
                    (defn shape-%1$d [xs n]
                      (when (pos? n)
                        (let [tally (for [x :in xs :when (pos? x)] (* x n))]
                          (cond
                            (empty? tally) nil
                            (= 1 (count tally)) (first tally)
                            (if-let [head (first tally)]
                              (-> head (+ n) (* 2))
                              n)))))
                    PHEL,
                $i,
            );
        }

        return '(ns ' . self::FIXTURE_NS . ")\n\n" . implode("\n\n", $forms) . "\n";
    }
}
