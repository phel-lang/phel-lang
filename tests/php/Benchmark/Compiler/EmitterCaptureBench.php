<?php

declare(strict_types=1);

namespace PhelTest\Benchmark\Compiler;

use Phel;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFacade;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\LoadClasspath;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;
use PhpBench\Benchmark\Metadata\Annotations\BeforeMethods;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use RuntimeException;

/**
 * Emitter capture-as-expression micro-benchmark (issue #2565).
 *
 * `OutputEmitter::captureNodeAsExpression()` is hit on every `if`
 * ternary fallback, every `and`/`or` short-circuit operand, and every
 * inlined type-predicate argument. The fixture is deliberately dense in
 * exactly those shapes so the emit timing is dominated by that capture
 * path.
 *
 * `setUp` bootstraps `phel.core` once per phpbench subprocess (so the
 * referenced builtins resolve), analyses the fixture forms into AST
 * nodes once, then each revolution re-emits the pre-analysed nodes via
 * the statement emitter. Re-emitting the same AST keeps lexer / parser /
 * analyzer cost out of the measurement, so deltas attribute cleanly to
 * the emitter (and the capture helper in particular).
 *
 * @BeforeMethods("setUp")
 */
final class EmitterCaptureBench
{
    private const int FORM_COUNT = 60;

    private const string FIXTURE_NS = 'phel.bench.emittercapture';

    private StatementEmitterInterface $statementEmitter;

    /** @var list<AbstractNode> */
    private array $nodes = [];

    public function setUp(): void
    {
        $projectRoot = __DIR__ . '/../../../../';

        Phel::bootstrap($projectRoot);
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
        LoadClasspath::publish([$projectRoot . 'src/phel']);

        new BuildFacade()->evalFile($projectRoot . 'src/phel/core.phel');
        BuildFacade::enableBuildMode();

        $compilerFacade = new CompilerFacade();
        $this->statementEmitter = new CompilerFactory()->createStatementEmitter(false);

        $stream = $compilerFacade->lexString($this->buildFixtureSource(), LexerInterface::DEFAULT_SOURCE);

        while (true) {
            $parseTree = $compilerFacade->parseNext($stream);
            if (!$parseTree instanceof NodeInterface) {
                break;
            }

            if ($parseTree instanceof TriviaNodeInterface) {
                continue;
            }

            $readerResult = $compilerFacade->read($parseTree);
            $this->nodes[] = $compilerFacade->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()->withReturnContext(),
            );
        }

        if ($this->nodes === []) {
            throw new RuntimeException('emitter capture bench analysed no forms');
        }
    }

    /**
     * @Revs(100)
     *
     * @Iterations(5)
     */
    public function bench_emit_capture_dense(): void
    {
        foreach ($this->nodes as $node) {
            $this->statementEmitter->emitNode($node, false);
        }
    }

    /**
     * A deterministic fixture dense in the three call sites that hit
     * `captureNodeAsExpression`: `or`/`and` short-circuit chains (the
     * `LetEmitter` operand path), `if` whose branches are simple
     * expressions (the `IfEmitter` return-ternary path), and type
     * predicates on tagged locals (the `TypePredicateCallEmitter` path).
     */
    private function buildFixtureSource(): string
    {
        $forms = ['(ns ' . self::FIXTURE_NS . ')'];

        for ($i = 0; $i < self::FORM_COUNT; ++$i) {
            $forms[] = <<<PHEL
                (defn pick-{$i} [^int a ^int b c]
                  (let [chosen (or a b {$i})
                        guarded (and a b)
                        flag (if (pos? a) chosen guarded)]
                    (if (and (int? a) (or (zero? b) (neg? b)))
                      (if (int? c) (+ a b {$i}) flag)
                      (or guarded chosen {$i}))))
                PHEL;
        }

        return implode("\n\n", $forms) . "\n";
    }
}
