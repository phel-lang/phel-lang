<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Analyzer;
use Phel\AnalyzerInterface;
use Phel\Emitter;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;
use Phel\GlobalEnvironment;
use Phel\Lexer;
use Phel\NodeEnvironment;
use Phel\Reader;
use Phel\ReaderResult;

final class EvalCompiler
{
    private Lexer $lexer;
    private Reader $reader;
    private AnalyzerInterface $analyzer;
    private Emitter $emitter;

    public function __construct(GlobalEnvironment $globalEnv)
    {
        $this->lexer = new Lexer();
        $this->reader = new Reader($globalEnv);
        $this->analyzer = new Analyzer($globalEnv);
        $this->emitter = Emitter::createWithSourceMap();
    }

    /**
     * Evaluates a provided Phel code.
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $code)
    {
        try {
            $tokenStream = $this->lexer->lexString($code);
            $readerResult = $this->reader->readNext($tokenStream);
            if (!$readerResult) {
                return null;
            }

            return $this->evalNode($readerResult);
        } catch (ReaderException $e) {
            throw new CompilerException($e, $e->getCodeSnippet());
        }
    }

    /**
     * @return mixed
     */
    private function evalNode(ReaderResult $readerResult)
    {
        try {
            $node = $this->analyzer->analyze(
                $readerResult->getAst(),
                NodeEnvironment::empty()->withContext(NodeEnvironment::CONTEXT_RETURN)
            );

            $code = $this->emitter->emitNodeAsString($node);

            return $this->emitter->evalCode($code);
        } catch (AnalyzerException $e) {
            throw new CompilerException($e, $readerResult->getCodeSnippet());
        }
    }
}
