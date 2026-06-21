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

/**
 * Analyzer / type-inference body-walk micro-benchmark.
 *
 * `ParamTypeInferrer` and `ReturnTypeInferrer` walk every `defn` body
 * during analysis to graft `:tag` metadata onto binding symbols. This
 * bench measures that walk over a deterministic fixture of ~50
 * arithmetic and collection `defn`s.
 *
 * `setUp` bootstraps `phel.core` once per phpbench subprocess (so `+`,
 * `map`, `reduce`, `get`, `defn` and friends resolve) and enables
 * build mode so the same forms can be re-analysed each revolution
 * without tripping the duplicate-definition guard. The fixture source
 * is built inline — no `.phel/cache` dependency, no wall-clock data.
 *
 * Each revolution re-lexes, re-parses, reads and analyses every form,
 * so the timing is dominated by the analyzer + type-inference passes.
 *
 * @BeforeMethods("setUp")
 */
final class TypeInferenceBench
{
    private const int DEFN_COUNT = 50;

    private const string FIXTURE_NS = 'phel.bench.typeinference';

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
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function bench_analyze_defns(): void
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
     * `DEFN_COUNT` arithmetic/collection `defn`s. Bodies mix numeric
     * operators (`+`, `-`, `*`) with collection builtins (`map`,
     * `reduce`, `filter`, `get`, `conj`) so both inferrers walk a
     * representative tree.
     */
    private function buildFixtureSource(): string
    {
        $forms = ['(ns ' . self::FIXTURE_NS . ')'];

        for ($i = 0; $i < self::DEFN_COUNT; ++$i) {
            $forms[] = <<<PHEL
                (defn compute-{$i} [xs n]
                  (let [doubled (map (fn [x] (* x 2)) xs)
                        kept (filter (fn [x] (> x {$i})) doubled)
                        total (reduce (fn [acc x] (+ acc x)) 0 kept)
                        bag (conj [] total n)]
                    (- (+ total n {$i}) (get bag 0))))
                PHEL;
        }

        return implode("\n\n", $forms) . "\n";
    }
}
