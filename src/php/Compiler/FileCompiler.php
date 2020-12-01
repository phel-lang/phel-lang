<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\ReadModel\ReaderResult;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;

final class FileCompiler implements FileCompilerInterface
{
    private LexerInterface $lexer;
    private ReaderInterface $reader;
    private AnalyzerInterface $analyzer;
    private EmitterInterface $emitter;

    public function __construct(
        LexerInterface $lexer,
        ReaderInterface $reader,
        AnalyzerInterface $analyzer,
        EmitterInterface $emitter
    ) {
        $this->lexer = $lexer;
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
                $readerResult = $this->reader->readNext($tokenStream);
                // If we reached the end exit this loop
                if (!$readerResult) {
                    break;
                }

                $code .= $this->analyzeAndEvalNode($readerResult);
            } catch (ReaderException $e) {
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
