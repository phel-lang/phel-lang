<?php

declare(strict_types=1);

namespace PhelTest\Integration\Compiler;

use Gacela\Framework\Gacela;
use Phel\Build\BuildFacade;
use Phel\Compiler\CompilerFactory;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function count;

/**
 * The evaluator now runs the file emitter's output for any form whose emission
 * never asked a mode question with a statement-mode-divergent answer, instead
 * of emitting that form a second time.
 *
 * That is safe only while "did not diverge" really does imply "same bytes and
 * same source map", and the failure mode if it stops holding is silent: no
 * crash and no failing assertion anywhere else, just wrong `.phel` line numbers
 * in the errors a cold compile reports, because the per-form map is sliced out
 * of the whole-file one rather than generated on its own.
 *
 * So both halves are pinned here, per form, against the statement emission they
 * replace.
 */
final class SingleEmissionEquivalenceTest extends AbstractCompilerRuntimeTestCase
{
    private CompilerFactory $compilerFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compilerFactory = Gacela::get(CompilerFactory::class);
    }

    public function test_a_reused_emission_matches_the_statement_emission(): void
    {
        BuildFacade::enableBuildMode();

        try {
            $outcomes = $this->emitBothWays($this->fixtureSource());
        } finally {
            BuildFacade::disableBuildMode();
        }

        self::assertNotSame([], $outcomes, 'the fixture produced no forms to compare');

        foreach ($outcomes as $outcome) {
            if ($outcome['reused'] === false) {
                continue;
            }

            self::assertSame($outcome['statementCode'], $outcome['fileCode'], $outcome['label']);
            self::assertSame($outcome['statementMap'], $outcome['fileMap'], $outcome['label']);
        }
    }

    /**
     * The point of the change is that reuse is the common case; a regression
     * that made everything fall back would keep the assertions above green
     * while quietly restoring the second emission.
     */
    public function test_most_forms_are_reused(): void
    {
        BuildFacade::enableBuildMode();

        try {
            $outcomes = $this->emitBothWays($this->fixtureSource());
        } finally {
            BuildFacade::disableBuildMode();
        }

        $reused = 0;
        foreach ($outcomes as $outcome) {
            $reused += (int) $outcome['reused'];
        }

        self::assertGreaterThan(count($outcomes) / 2, $reused);
    }

    /**
     * A form that declares a PHP namespace or a type is emitted differently in
     * file mode, so it must keep both emissions.
     */
    public function test_a_mode_dependent_form_is_not_reused(): void
    {
        BuildFacade::enableBuildMode();

        try {
            $outcomes = $this->emitBothWays('(ns test-single-emission.ns-form)');
        } finally {
            BuildFacade::disableBuildMode();
        }

        self::assertCount(1, $outcomes);
        self::assertFalse($outcomes[0]['reused']);
    }

    /**
     * @return list<array{label: string, reused: bool, fileCode: string, fileMap: string, statementCode: string, statementMap: string}>
     */
    private function emitBothWays(string $source): array
    {
        $compilerFacade = $this->compilerFacade;
        $stream = $compilerFacade->lexString($source, LexerInterface::DEFAULT_SOURCE);

        $outcomes = [];
        $index = 0;

        while (true) {
            $parseTree = $compilerFacade->parseNext($stream);
            if (!$parseTree instanceof NodeInterface) {
                break;
            }

            if ($parseTree instanceof TriviaNodeInterface) {
                continue;
            }

            $readerResult = $compilerFacade->read($parseTree);

            Symbol::resetGen();
            $node = $compilerFacade->analyze($readerResult->getAst(), NodeEnvironment::empty());

            Symbol::resetGen();
            $fileEmitter = $this->compilerFactory->createFileEmitter(true);
            $fileEmitter->startFile('single-emission.phel');
            $captured = $fileEmitter->emitNodeCapturing($node, true);

            Symbol::resetGen();
            $statement = $this->compilerFactory->createStatementEmitter(true)->emitNode($node, true);

            $outcomes[] = [
                'label' => 'form #' . $index,
                'reused' => $captured instanceof EmitterResult,
                'fileCode' => $captured?->getPhpCode() ?? '',
                'fileMap' => $captured?->getSourceMap() ?? '',
                'statementCode' => $statement->getPhpCode(),
                'statementMap' => $statement->getSourceMap(),
            ];

            ++$index;
        }

        return $outcomes;
    }

    /**
     * Shapes that between them reach the emitter paths worth pinning: a def
     * with metadata, a closure, a conditional, a loop, interop, a collection
     * literal and a `try`. Written inline so the fixture cannot drift with a
     * source file it happens to point at.
     */
    private function fixtureSource(): string
    {
        return <<<'PHEL'
            (def answer 42)

            (def greeting "hello")

            (defn add-one [x] (+ x 1))

            (defn classify [n]
              (cond
                (< n 0) :negative
                (= n 0) :zero
                :positive))

            (defn total [xs]
              (loop [acc 0 remaining xs]
                (if (empty? remaining)
                  acc
                  (recur (+ acc (first remaining)) (next remaining)))))

            (defn shapes []
              {:vector [1 2 3] :set (set [1 2]) :list '(1 2)})

            (defn guarded [x]
              (try
                (/ 10 x)
                (catch \Throwable e :boom)))

            (defn interop []
              (php/strlen "abc"))
            PHEL;
    }
}
