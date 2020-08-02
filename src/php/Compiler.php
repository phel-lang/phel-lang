<?php

declare(strict_types=1);

namespace Phel;

use Phel\Emitter\EvalEmitter;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;

final class Compiler
{
    private Lexer $lexer;
    private Reader $reader;
    private Analyzer $analyzer;
    private Emitter $emitter;
    private EvalEmitter $evalEmitter;

    public function __construct(GlobalEnvironment $globalEnv)
    {
        $this->lexer = new Lexer();
        $this->reader = new Reader($globalEnv);
        $this->analyzer = new Analyzer($globalEnv);
        $this->emitter = Emitter::createWithSourceMap();
        $this->evalEmitter = new EvalEmitter();
    }

    public function compileFile(string $filename): string
    {
        return $this->compileString(file_get_contents($filename), $filename);
    }

    public function compileString(string $code, string $source = 'string'): string
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

                try {
                    $nodes = $this->analyzer->analyzeInEmptyEnv($readerResult->getAst());
                } catch (AnalyzerException $e) {
                    throw new CompilerException($e, $readerResult->getCodeSnippet());
                }

                $code .= $this->emitter->emitNodeAndEval($nodes);
            } catch (ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }

        return $code;
    }

    /**
     * Evaluates a provided Phel code
     *
     * @return mixed The result of the executed code.
     */
    public function eval(string $code)
    {
        try {
            $tokenStream = $this->lexer->lexString($code);
            $readerResult = $this->reader->readNext($tokenStream);

            if (!$readerResult) {
                return null;
            }

            try {
                $node = $this->analyzer->analyze(
                    $readerResult->getAst(),
                    NodeEnvironment::empty()->withContext(NodeEnvironment::CTX_RET)
                );
                $code = $this->emitter->emitNodeAsString($node);

                return $this->evalEmitter->eval($code);
            } catch (AnalyzerException $e) {
                throw new CompilerException($e, $readerResult->getCodeSnippet());
            }
        } catch (ReaderException $e) {
            throw new CompilerException($e, $e->getCodeSnippet());
        }
    }
}
