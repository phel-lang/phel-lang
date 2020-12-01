<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\ReadModel\ReaderResult;
use Phel\Exceptions\AnalyzerException;
use Phel\Exceptions\CompilerException;
use Phel\Exceptions\ReaderException;

final class EvalCompiler implements EvalCompilerInterface
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

    /**
     * Evaluates a provided Phel code.
     *
     * @return mixed The result of the executed code
     *
     * @throws CompilerException|ReaderException
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
     *
     * @throws CompilerException
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
