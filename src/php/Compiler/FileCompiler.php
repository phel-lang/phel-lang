<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Analyzer;
use Phel\AnalyzerInterface;
use Phel\Compiler\Emitter;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;
use Phel\GlobalEnvironmentInterface;
use Phel\Compiler\Lexer;
use Phel\NodeEnvironment;
use Phel\Compiler\Reader;
use Phel\Compiler\ReaderResult;

final class FileCompiler
{
    private Lexer $lexer;
    private Reader $reader;
    private AnalyzerInterface $analyzer;
    private Emitter $emitter;

    public function __construct(GlobalEnvironmentInterface $globalEnv)
    {
        $this->lexer = new Lexer();
        $this->reader = new Reader($globalEnv);
        $this->analyzer = new Analyzer($globalEnv);
        $this->emitter = Emitter::createWithSourceMap();
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
