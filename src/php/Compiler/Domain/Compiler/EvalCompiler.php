<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Compiler;

use Phel\Compiler\Domain\Analyzer\AnalyzerInterface;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Emitter\StatementEmitterInterface;
use Phel\Compiler\Domain\Evaluator\EvaluatorInterface;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\LexerInterface;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ParserInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Domain\Reader\ReaderInterface;
use Phel\Compiler\Infrastructure\CompileOptions;

final class EvalCompiler implements EvalCompilerInterface
{
    private LexerInterface $lexer;
    private ParserInterface $parser;
    private ReaderInterface $reader;
    private AnalyzerInterface $analyzer;
    private StatementEmitterInterface $emitter;
    private EvaluatorInterface $evaluator;

    public function __construct(
        LexerInterface $lexer,
        ParserInterface $parser,
        ReaderInterface $reader,
        AnalyzerInterface $analyzer,
        StatementEmitterInterface $emitter,
        EvaluatorInterface $evaluator
    ) {
        $this->lexer = $lexer;
        $this->parser = $parser;
        $this->reader = $reader;
        $this->analyzer = $analyzer;
        $this->emitter = $emitter;
        $this->evaluator = $evaluator;
    }

    /**
     * Evaluates a provided Phel code.
     *
     * @throws CompiledCodeIsMalformedException
     * @throws CompilerException
     * @throws FileException
     * @throws LexerValueException
     * @throws UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function evalString(string $phelCode, CompileOptions $compileOptions): mixed
    {
        $tokenStream = $this->lexer->lexString($phelCode, $compileOptions->getSource(), $compileOptions->getStartingLine());

        $result = null;
        while (true) {
            try {
                $parseTree = $this->parser->parseNext($tokenStream);

                if (!$parseTree) {
                    return $result;
                }

                if (!$parseTree instanceof TriviaNodeInterface) {
                    $readerResult = $this->reader->read($parseTree);
                    $node = $this->analyze($readerResult);

                    $result = $this->evalNode($node, $compileOptions);
                }
            } catch (UnfinishedParserException $e) {
                throw $e;
            } catch (AbstractParserException|ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }

        return $result;
    }

    public function evalForm($form, CompileOptions $compileOptions): mixed
    {
        $node = $this->analyzer->analyze($form, NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_RETURN));
        return $this->evalNode($node, $compileOptions);
    }
    /**
     * @throws CompilerException
     */
    private function analyze(ReaderResult $readerResult): AbstractNode
    {
        try {
            return $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_RETURN)
            );
        } catch (AnalyzerException $e) {
            throw new CompilerException($e, $readerResult->getCodeSnippet());
        }
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     *
     * @return mixed The result of the executed code
     */
    private function evalNode(AbstractNode $node, CompileOptions $compileOptions)
    {
        $code = $this->emitter->emitNode($node, $compileOptions->isSourceMapsEnabled())->getCodeWithSourceMap();

        return $this->evaluator->eval($code);
    }
}
