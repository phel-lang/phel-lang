<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Emitter\EmitterInterface;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Reader\ReaderInterface;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ParserException;
use Phel\Exceptions\ReaderException;

final class FileCompiler implements FileCompilerInterface
{
    private LexerInterface $lexer;
    private ParserInterface $parser;
    private ReaderInterface $reader;
    private AnalyzerInterface $analyzer;
    private EmitterInterface $emitter;

    public function __construct(
        LexerInterface $lexer,
        ParserInterface $parser,
        ReaderInterface $reader,
        AnalyzerInterface $analyzer,
        EmitterInterface $emitter
    ) {
        $this->lexer = $lexer;
        $this->parser = $parser;
        $this->reader = $reader;
        $this->analyzer = $analyzer;
        $this->emitter = $emitter;
    }

    public function compile(string $filename): string
    {
        $code = file_get_contents($filename);
        $tokenStream = $this->lexer->lexString($code, $filename);
        $code = '';

        while (true) {
            try {
                $parseTree = $this->parser->parseNext($tokenStream);

                // If we reached the end exit this loop
                if (!$parseTree) {
                    break;
                }

                if (!$parseTree instanceof TriviaNodeInterface) {
                    $readerResult = $this->reader->read($parseTree);
                    $code .= $this->analyzeAndEvalNode($readerResult);
                }
            } catch (ParserException|ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }

        return $code;
    }

    private function analyzeAndEvalNode(ReaderResult $readerResult): string
    {
        try {
            $node = $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()
            );
        } catch (AnalyzerException $e) {
            throw new CompilerException($e, $readerResult->getCodeSnippet());
        }

        return $this->emitter->emitNodeAndEval($node);
    }
}
