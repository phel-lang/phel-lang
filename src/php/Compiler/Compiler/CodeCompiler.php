<?php

declare(strict_types=1);

namespace Phel\Compiler\Compiler;

use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Emitter\EmitterInterface;
use Phel\Compiler\Evaluator\EvaluatorInterface;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Compiler\Reader\ReaderInterface;

final class CodeCompiler implements CodeCompilerInterface
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
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     * @throws LexerValueException
     */
    public function compile(string $phelCode, string $source = self::DEFAULT_SOURCE): string
    {
        $tokenStream = $this->lexer->lexString($phelCode, $source);
        $phpCode = '';

        while (true) {
            try {
                $parseTree = $this->parser->parseNext($tokenStream);
                // If we reached the end exit this loop
                if (!$parseTree) {
                    break;
                }

                if (!$parseTree instanceof TriviaNodeInterface) {
                    $readerResult = $this->reader->read($parseTree);
                    $node = $this->analyze($readerResult);
                    $phpCode .= $this->emitNode($node);
                }
            } catch (AbstractParserException|ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }

        return $phpCode;
    }

    /**
     * @throws CompilerException
     */
    private function analyze(ReaderResult $readerResult): AbstractNode
    {
        try {
            return $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()
            );
        } catch (AnalyzerException $e) {
            throw new CompilerException($e, $readerResult->getCodeSnippet());
        }
    }

    /**
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    private function emitNode(AbstractNode $node): string
    {
        $code = $this->emitter->emitNode($node);
        $this->evaluator->eval($code);

        return $code;
    }
}
