<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Compiler;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Emitter\EmitterResult;
use Phel\Transpiler\Domain\Emitter\FileEmitterInterface;
use Phel\Transpiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Transpiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\TrarnspiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Exceptions\TranspilerException;
use Phel\Transpiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Transpiler\Domain\Lexer\LexerInterface;
use Phel\Transpiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Transpiler\Domain\Parser\ParserInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Transpiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Transpiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Transpiler\Domain\Reader\ReaderInterface;
use Phel\Transpiler\Infrastructure\TranspileOptions;

final readonly class CodeTranspiler implements CodeCompilerInterface
{
    public function __construct(
        private LexerInterface $lexer,
        private ParserInterface $parser,
        private ReaderInterface $reader,
        private AnalyzerInterface $analyzer,
        private StatementEmitterInterface $statementEmitter,
        private FileEmitterInterface $fileEmitter,
        private EvaluatorInterface $evaluator,
    ) {
    }

    /**
     * @throws TranspilerException
     * @throws TrarnspiledCodeIsMalformedException
     * @throws FileException
     * @throws LexerValueException
     */
    public function compileString(string $phelCode, TranspileOptions $compileOptions): EmitterResult
    {
        $tokenStream = $this->lexer->lexString($phelCode, $compileOptions->getSource(), $compileOptions->getStartingLine());

        $this->fileEmitter->startFile($compileOptions->getSource());
        while (true) {
            try {
                $parseTree = $this->parser->parseNext($tokenStream);
                // If we reached the end exit this loop
                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if (!$parseTree instanceof TriviaNodeInterface) {
                    $readerResult = $this->reader->read($parseTree);
                    $node = $this->analyze($readerResult);
                    // We need to evaluate every statement because we may need it for macros.
                    $this->emitNode($node, $compileOptions);
                }
            } catch (AbstractParserException|ReaderException $e) {
                throw new TranspilerException($e, $e->getCodeSnippet());
            }
        }

        return $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());
    }

    public function compileForm(float|bool|int|string|TypeInterface|null $form, TranspileOptions $compileOptions): EmitterResult
    {
        $this->fileEmitter->startFile($compileOptions->getSource());
        $node = $this->analyzer->analyze($form, NodeEnvironment::empty());
        $this->emitNode($node, $compileOptions);
        return $this->fileEmitter->endFile($compileOptions->isSourceMapsEnabled());
    }

    /**
     * @throws TranspilerException
     */
    private function analyze(ReaderResult $readerResult): AbstractNode
    {
        try {
            return $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty(),
            );
        } catch (AnalyzerException $analyzerException) {
            throw new TranspilerException($analyzerException, $readerResult->getCodeSnippet());
        }
    }

    /**
     * @throws TrarnspiledCodeIsMalformedException
     * @throws FileException
     */
    private function emitNode(AbstractNode $node, TranspileOptions $compileOptions): void
    {
        $this->fileEmitter->emitNode($node);

        $code = $this->statementEmitter->emitNode($node, $compileOptions->isSourceMapsEnabled())->getCodeWithSourceMap();
        $this->evaluator->eval($code);
    }
}