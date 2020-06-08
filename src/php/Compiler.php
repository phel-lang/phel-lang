<?php

namespace Phel;

use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;

class Compiler
{
    public function compileFile(string $filename, GlobalEnvironment $globalEnv)
    {
        return $this->compileString(file_get_contents($filename), $globalEnv, $filename);
    }

    public function compileString(string $code, GlobalEnvironment $globalEnv, string $source = 'string'): string
    {
        $lexer = new Lexer();
        $reader = new Reader();
        $analzyer = new Analyzer($globalEnv);
        $emitter = new Emitter();
        $tokenStream = $lexer->lexString($code, $source);
        $code = '';

        while (true) {
            try {
                $readerResult = $reader->readNext($tokenStream);

                // If we reached the end exit this loop
                if (!$readerResult) {
                    break;
                }

                try {
                    $nodes = $analzyer->analyze($readerResult->getAst());
                } catch (AnalyzerException $e) {
                    throw new CompilerException($e, $readerResult->getCodeSnippet());
                }

                $code .= $emitter->emitAndEval($nodes);
            } catch (ReaderException $e) {
                throw new CompilerException($e, $e->getCodeSnippet());
            }
        }

        return $code;
    }
}
