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
 * Analyzer wide-`let` / destructuring binding-scope micro-benchmark.
 *
 * Analysing `let`/`loop` bindings updates two derived index arrays
 * (`localsByName`, `shadowedReverse`) on the `NodeEnvironment` once per
 * binding. This bench drives a deterministic fixture of `defn`s, each
 * with a single very wide `let` scope (many scalar bindings plus a few
 * map/vector destructuring forms that expand into extra bindings), so
 * the timing is dominated by the per-binding environment updates.
 *
 * `setUp` bootstraps `phel.core` once per phpbench subprocess and enables
 * build mode so the same forms can be re-analysed each revolution
 * without tripping the duplicate-definition guard. The fixture source is
 * built inline — no `.phel/cache` dependency, no wall-clock data.
 *
 * @BeforeMethods("setUp")
 */
final class WideLetBindingBench
{
    private const int DEFN_COUNT = 30;

    private const int BINDINGS_PER_LET = 40;

    private const string FIXTURE_NS = 'phel.bench.wideletbinding';

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

        // Allow the same `defn`s to be re-analysed across revolutions
        // without the duplicate-definition guard firing.
        BuildFacade::enableBuildMode();

        $this->compilerFacade = new CompilerFacade();
        $this->fixtureSource = $this->buildFixtureSource();
    }

    /**
     * @Revs(30)
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
     * Builds a deterministic fixture: an `ns` form followed by
     * `DEFN_COUNT` `defn`s. Each body opens one wide `let` with
     * `BINDINGS_PER_LET` scalar bindings that chain off each other, plus
     * a map and a vector destructuring form (each expanding into several
     * extra bindings), then returns a sum over the bound names.
     */
    private function buildFixtureSource(): string
    {
        $forms = ['(ns ' . self::FIXTURE_NS . ')'];

        for ($i = 0; $i < self::DEFN_COUNT; ++$i) {
            $bindings = ['b0 n'];
            for ($j = 1; $j < self::BINDINGS_PER_LET; ++$j) {
                $prev = $j - 1;
                $bindings[] = sprintf('b%d (+ b%d %d)', $j, $prev, $j);
            }

            // Destructuring bindings expand into extra shadowed locals.
            $bindings[] = '{:x dx :y dy} {:x b0 :y b1}';
            $bindings[] = '[v0 v1 v2] [b0 b1 b2]';

            $bindingStr = implode("\n        ", $bindings);
            $last = self::BINDINGS_PER_LET - 1;

            $forms[] = <<<PHEL
                (defn wide-{$i} [n]
                  (let [{$bindingStr}]
                    (+ b{$last} dx dy v0 v1 v2)))
                PHEL;
        }

        return implode("\n\n", $forms) . "\n";
    }
}
