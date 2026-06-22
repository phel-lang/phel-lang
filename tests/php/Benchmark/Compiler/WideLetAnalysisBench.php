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
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

use function sprintf;

/**
 * Wide-`let` analysis micro-benchmark.
 *
 * Analyzing a `let`/`loop` binding vector updates the derived
 * `localsByName` / `shadowedReverse` indexes once per binding. Rebuilding
 * those indexes from scratch each time is O(N) per binding — O(N^2) over
 * a scope of N bindings; the incremental `withLocalAndShadow` brings the
 * per-binding work back to O(1). This bench analyzes a single `defn`
 * whose body is a `let` with many sequential bindings (each init reads
 * the previous one), the shape where that asymptotic difference shows.
 *
 * `setUp` bootstraps `phel.core` once per phpbench subprocess and enables
 * build mode so the same form can be re-analyzed each revolution. The
 * fixture source is built inline — no `.phel/cache` dependency.
 *
 * @BeforeMethods("setUp")
 */
final class WideLetAnalysisBench
{
    private const int BINDING_COUNT = 120;

    private const string FIXTURE_NS = 'phel.bench.widelet';

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
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function bench_analyze_wide_let(): void
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
     * Builds an `ns` form plus a single `defn` whose `let` binds
     * `BINDING_COUNT` locals, each init referencing the previous binding
     * so the analyzer must keep the locals/shadow indexes in scope as it
     * walks. The body sums the first and last binding so none are dropped.
     */
    private function buildFixtureSource(): string
    {
        $bindings = ['b0 n'];
        for ($i = 1; $i < self::BINDING_COUNT; ++$i) {
            $prev = $i - 1;
            $bindings[] = sprintf('b%d (+ b%d %d)', $i, $prev, $i);
        }

        $last = self::BINDING_COUNT - 1;
        $bindingStr = implode("\n        ", $bindings);

        $defn = <<<PHEL
            (defn wide [n]
              (let [{$bindingStr}]
                (+ b0 b{$last})))
            PHEL;

        return '(ns ' . self::FIXTURE_NS . ")\n\n" . $defn . "\n";
    }
}
