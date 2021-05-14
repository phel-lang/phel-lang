<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Emitter\EmitterInterface;
use Phel\Compiler\Evaluator\EvaluatorInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Compiler\Reader\ReaderInterface;

final class EvalCompiler implements EvalCompilerInterface
{
    private LexerInterface $lexer;
    private ParserInterface $parser;
    private ReaderInterface $reader;
    private AnalyzerInterface $analyzer;
    private EmitterInterface $emitter;
    private EvaluatorInterface $evaluator;

    public function __construct(
        LexerInterface $lexer,
        ParserInterface $parser,
        ReaderInterface $reader,
        AnalyzerInterface $analyzer,
        EmitterInterface $emitter,
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
    public function eval(string $code, int $startingLine = 1)
    {
        try {
            $tokenStream = $this->lexer->lexString($code, LexerInterface::DEFAULT_SOURCE, $startingLine);
            $parseTree = $this->parser->parseNext($tokenStream);

            if (!$parseTree || $parseTree instanceof TriviaNodeInterface) {
                return null;
            }

            $readerResult = $this->reader->read($parseTree);

            return $this->evalNode($readerResult);
        } catch (UnfinishedParserException $e) {
            throw $e;
        } catch (AbstractParserException|ReaderException $e) {
            throw new CompilerException($e, $e->getCodeSnippet());
        }
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws CompilerException
     * @throws FileException
     *
     * @return mixed
     */
    private function evalNode(ReaderResult $readerResult)
    {
        try {
            $node = $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()->withContext(NodeEnvironmentInterface::CONTEXT_RETURN)
            );

            $code = $this->emitter->emitNode($node);

            return $this->evaluator->eval($code);
        } catch (AnalyzerException $e) {
            throw new CompilerException($e, $readerResult->getCodeSnippet());
        }
    }
}
