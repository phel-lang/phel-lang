<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Cache\CachedReaderResult;
use Phel\Compiler\Domain\Cache\ReaderResultCacheInterface;
use Phel\Compiler\Domain\Compiler\CodeCompilerInterface;
use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Emitter\FileEmitterInterface;
use Phel\Compiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Compiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Lang\ProfilerHookInterface;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompiledCodeIsMalformedException;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\FileException;
use Phel\Shared\Parser\Node\NodeInterface;
use Phel\Shared\Parser\Node\TriviaNodeInterface;

use function hrtime;

/**
 * @internal
 */
final readonly class CodeCompiler implements CodeCompilerInterface
{
    public function __construct(
        private LexerInterface $lexer,
        private ParserInterface $parser,
        private ReaderInterface $reader,
        private AnalyzerInterface $analyzer,
        private StatementEmitterInterface $statementEmitter,
        private FileEmitterInterface $fileEmitter,
        private EvaluatorInterface $evaluator,
        private ReaderResultCacheInterface $readerResultCache,
    ) {}

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws LexerValueException
     */
    public function compileString(string $phelCode, CompileOptions $compileOptions): EmitterResult
    {
        // Every deprecation this compile finds travels with the result, so
        // the compiled-code cache can replay it on a warm run (#3222).
        DeprecationWarnings::startRecording();
        try {
            $result = $this->doCompileString($phelCode, $compileOptions);
        } finally {
            $deprecations = DeprecationWarnings::stopRecording();
        }

        return $result->withDeprecations($deprecations);
    }

    public function compileForm(
        float|bool|int|string|TypeInterface|null $form,
        CompileOptions $compileOptions,
    ): EmitterResult {
        $this->analyzer->setOptimizationLevel($compileOptions->getOptimizationLevel());
        $this->fileEmitter->startFile($compileOptions->getSource());
        $node = $this->analyzer->analyze($form, NodeEnvironment::empty());
        $this->emitNode($node, $compileOptions);
        return $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws LexerValueException
     */
    private function doCompileString(string $phelCode, CompileOptions $compileOptions): EmitterResult
    {
        $hook = Registry::$profilerHook;
        $source = $compileOptions->getSource();
        $optimizationLevel = $compileOptions->getOptimizationLevel();
        $this->analyzer->setOptimizationLevel($optimizationLevel);

        $cachedReaderResults = $this->readerResultCache->load($phelCode, $optimizationLevel);
        if ($cachedReaderResults !== null) {
            return $this->compileReaderResults($cachedReaderResults, $compileOptions, $hook, $source);
        }

        $tokenStream = $this->timed($hook, 'lex', $source, fn(): TokenStream => $this->lexer->lexString(
            $phelCode,
            $source,
            $compileOptions->getStartingLine(),
        ));

        $entries = [];
        $this->fileEmitter->startFile($source);
        while (true) {
            try {
                $parseTree = $this->timed($hook, 'parse', $source, fn(): ?NodeInterface => $this->parser->parseNext($tokenStream));

                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                $genBefore = Symbol::genCounter();
                $readerResult = $this->timed($hook, 'read', $source, fn(): ReaderResult => $this->reader->read($parseTree));
                $entries[] = new CachedReaderResult($readerResult, Symbol::genCounter() - $genBefore);
                $this->analyzeAndEmit($readerResult, $compileOptions, $hook, $source);
            } catch (AbstractParserException|ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }

        $this->readerResultCache->save($phelCode, $optimizationLevel, $entries);

        return $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());
    }

    /**
     * Warm path: replay cached reader results through analysis + emission,
     * skipping lex/parse/read. The cached forms are analysed and evaluated in
     * order, reproducing a cold compile's output; each form's recorded
     * read-phase gensym delta is replayed first so the shared gensym counter
     * follows the trajectory the skipped read would have (auto-gensym `x#` and
     * the short-fn reader consume gensyms at read time).
     *
     * Replayed forms are deserialized Phel values, so anything used as a map
     * key must compare by value, not instance identity (see Keyword::equals) —
     * otherwise a cached keyword-keyed lookup silently misses on a warm replay.
     *
     * @param list<CachedReaderResult> $entries
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    private function compileReaderResults(
        array $entries,
        CompileOptions $compileOptions,
        ?ProfilerHookInterface $hook,
        string $source,
    ): EmitterResult {
        $this->fileEmitter->startFile($source);
        foreach ($entries as $entry) {
            Symbol::advanceGenCounter($entry->gensymDelta);
            $this->analyzeAndEmit($entry->readerResult, $compileOptions, $hook, $source);
        }

        return $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    private function analyzeAndEmit(
        ReaderResult $readerResult,
        CompileOptions $compileOptions,
        ?ProfilerHookInterface $hook,
        string $source,
    ): void {
        $node = $this->timed($hook, 'analyze', $source, fn(): AbstractNode => $this->analyze($readerResult, $compileOptions));
        // We need to evaluate every statement because we may need it for macros.
        $this->timed($hook, 'emit', $source, fn() => $this->emitNode($node, $compileOptions));
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private function timed(?ProfilerHookInterface $hook, string $phase, string $source, callable $fn): mixed
    {
        if (!$hook instanceof ProfilerHookInterface) {
            return $fn();
        }

        $start = hrtime(true);
        $result = $fn();
        $hook->recordPhase($phase, $source, (hrtime(true) - $start) / 1_000_000);

        return $result;
    }

    /**
     * @throws CompilerException
     */
    private function analyze(ReaderResult $readerResult, CompileOptions $compileOptions): AbstractNode
    {
        $env = NodeEnvironment::empty();
        if ($compileOptions->isEmitAsExpression()) {
            $env = $env->withExpressionContext();
        }

        try {
            return $this->analyzer->analyze($readerResult->getAst(), $env);
        } catch (AnalyzerException $analyzerException) {
            throw new CompilerException($analyzerException, $readerResult->getCodeSnippet());
        }
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    private function emitNode(AbstractNode $node, CompileOptions $compileOptions): void
    {
        $genCounterBefore = Symbol::genCounter();
        $this->fileEmitter->emitNode($node);

        if ($compileOptions->isEmitOnly()) {
            // `phel compile` advertises "Does not evaluate"; skipping the
            // evaluator preserves the dry-run contract. Same-snippet
            // defmacros will not be available to later forms in this
            // mode, which is the documented trade-off.
            //
            // The statement emission is skipped with it: its only consumer is
            // the evaluator, so in this mode it emitted a whole second copy of
            // every form to throw away. What it did leave behind is the gensym
            // ids it consumed, and the next form's temporaries are numbered
            // after those, so the counter is advanced by the same delta the
            // file pass just used. Both passes walk the same nodes through the
            // same emitters here, so the deltas match; measuring rather than
            // assuming the delta keeps that true if they ever stop matching.
            Symbol::advanceGenCounter(Symbol::genCounter() - $genCounterBefore);
            return;
        }

        $phpCode = $this->statementEmitter
            ->emitNode($node, $compileOptions->isSourceMapsEnabled())
            ->getCodeWithSourceMap();

        $this->evaluator->eval($phpCode);
    }
}
