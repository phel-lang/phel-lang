<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Analyzer;
use Phel\Emitter;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;
use Phel\GlobalEnvironment;
use Phel\Lexer;
use Phel\Reader;
use Phel\ReaderResult;

final class FileCompiler
{
    private Lexer $lexer;
    private Reader $reader;
    private Analyzer $analyzer;
    private Emitter $emitter;

    public function __construct(GlobalEnvironment $globalEnv)
    {
        $this->lexer = new Lexer();
        $this->reader = new Reader($globalEnv);
        $this->analyzer = new Analyzer($globalEnv);
        $this->emitter = Emitter::createWithSourceMap();
    }

    public function compileFile(string $filename): string
    {
        return $this->compile(file_get_contents($filename), $filename);
    }

    private function compile(string $code, string $source = 'string'): string
    {
        $tokenStream = $this->lexer->lexString($code, $source);
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
            $node = $this->analyzer->analyzeInEmptyEnv($readerResult->getAst());
        } catch (AnalyzerException $e) {
            throw new CompilerException($e, $readerResult->getCodeSnippet());
        }

        return $this->emitter->emitNodeAndEval($node);
    }
}
