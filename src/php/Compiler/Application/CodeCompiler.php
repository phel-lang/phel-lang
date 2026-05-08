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
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Lang\ProfilerHookInterface;
use Phel\Lang\Registry;
use Phel\Lang\TypeInterface;

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

        if ($hook instanceof ProfilerHookInterface) {
            $tStart = hrtime(true);
            $tokenStream = $this->lexer->lexString($phelCode, $source, $compileOptions->getStartingLine());
            $hook->recordPhase('lex', $source, (hrtime(true) - $tStart) / 1_000_000);
        } else {
            $tokenStream = $this->lexer->lexString($phelCode, $source, $compileOptions->getStartingLine());
        }

        $this->fileEmitter->startFile($source);
        while (true) {
            try {
                if ($hook instanceof ProfilerHookInterface) {
                    $tStart = hrtime(true);
                    $parseTree = $this->parser->parseNext($tokenStream);
                    $hook->recordPhase('parse', $source, (hrtime(true) - $tStart) / 1_000_000);
                } else {
                    $parseTree = $this->parser->parseNext($tokenStream);
                }

                // If we reached the end exit this loop
                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if (!$parseTree instanceof TriviaNodeInterface) {
                    if ($hook instanceof ProfilerHookInterface) {
                        $tStart = hrtime(true);
                        $readerResult = $this->reader->read($parseTree);
                        $hook->recordPhase('read', $source, (hrtime(true) - $tStart) / 1_000_000);

                        $tStart = hrtime(true);
                        $node = $this->analyze($readerResult);
                        $hook->recordPhase('analyze', $source, (hrtime(true) - $tStart) / 1_000_000);

                        $tStart = hrtime(true);
                        $this->emitNode($node, $compileOptions);
                        $hook->recordPhase('emit', $source, (hrtime(true) - $tStart) / 1_000_000);
                    } else {
                        $readerResult = $this->reader->read($parseTree);
                        $node = $this->analyze($readerResult);
                        // We need to evaluate every statement because we may need it for macros.
                        $this->emitNode($node, $compileOptions);
                    }
                }
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

        $this->evaluator->eval($phpCode);
    }
}
