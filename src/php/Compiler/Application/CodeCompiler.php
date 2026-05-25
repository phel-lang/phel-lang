<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Compiler\CodeCompilerInterface;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Emitter\FileEmitterInterface;
use Phel\Compiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Compiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Lang\ProfilerHookInterface;
use Phel\Lang\Registry;
use Phel\Lang\TypeInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\Exceptions\CompiledCodeIsMalformedException;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\FileException;

use function hrtime;

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
    ) {}

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws LexerValueException
     */
    public function compileString(string $phelCode, CompileOptions $compileOptions): EmitterResult
    {
        $hook = Registry::$profilerHook;
        $source = $compileOptions->getSource();

        $tokenStream = $this->timed($hook, 'lex', $source, fn(): TokenStream => $this->lexer->lexString(
            $phelCode,
            $source,
            $compileOptions->getStartingLine(),
        ));

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

                $readerResult = $this->timed($hook, 'read', $source, fn(): ReaderResult => $this->reader->read($parseTree));
                $node = $this->timed($hook, 'analyze', $source, fn(): AbstractNode => $this->analyze($readerResult));
                // We need to evaluate every statement because we may need it for macros.
                $this->timed($hook, 'emit', $source, fn() => $this->emitNode($node, $compileOptions));
            } catch (AbstractParserException|ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }

        return $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());
    }

    public function compileForm(
        float|bool|int|string|TypeInterface|null $form,
        CompileOptions $compileOptions,
    ): EmitterResult {
        $this->fileEmitter->startFile($compileOptions->getSource());
        $node = $this->analyzer->analyze($form, NodeEnvironment::empty());
        $this->emitNode($node, $compileOptions);
        return $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());
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
    private function analyze(ReaderResult $readerResult): AbstractNode
    {
        try {
            return $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty(),
            );
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
        $this->fileEmitter->emitNode($node);

        $phpCode = $this->statementEmitter
            ->emitNode($node, $compileOptions->isSourceMapsEnabled())
            ->getCodeWithSourceMap();

        if ($compileOptions->isEmitOnly()) {
            // `phel compile` advertises "Does not evaluate"; skipping the
            // evaluator preserves the dry-run contract. Same-snippet
            // defmacros will not be available to later forms in this
            // mode, which is the documented trade-off.
            return;
        }

        $this->evaluator->eval($phpCode);
    }
}
