<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractFacade;
use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Compiler\CompileOptions;
use Phel\Compiler\Emitter\EmitterResult;
use Phel\Compiler\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Evaluator\Exceptions\FileException;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Lexer\Lexer;
use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Parser\ParserNode\FileNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Lang\TypeInterface;

/**
 * @method CompilerFactory getFactory()
 */
final class CompilerFacade extends AbstractFacade implements CompilerFacadeInterface
{
    /**
     * @param TypeInterface|string|float|int|bool|null $x
     *
     * @throws AnalyzerException
     */
    public function analyze($x, NodeEnvironmentInterface $env): AbstractNode
    {
        return $this->getFactory()
            ->createAnalyzer()
            ->analyze($x, $env);
    }

    /**
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $phelCode, CompileOptions $compileOptions)
    {
        return $this->getFactory()
            ->createEvalCompiler()
            ->eval($phelCode, $compileOptions);
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compile(string $phelCode, CompileOptions $compileOptions): EmitterResult
    {
        return $this->getFactory()
            ->createCodeCompiler($compileOptions)
            ->compile($phelCode, $compileOptions);
    }

    /**
     * @throws LexerValueException
     */
    public function lexString(
        string $code,
        string $source = Lexer::DEFAULT_SOURCE,
        bool $withLocation = true,
        int $startingLine = 1
    ): TokenStream {
        return $this->getFactory()
            ->createLexer($withLocation)
            ->lexString($code, $source, $startingLine);
    }

    /**
     * Reads the next expression from the token stream.
     * If the token stream reaches the end, null is returned.
     *
     * @param TokenStream $tokenStream The token stream to read
     *
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseNext(TokenStream $tokenStream): ?NodeInterface
    {
        return $this->getFactory()
            ->createParser()
            ->parseNext($tokenStream);
    }

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseAll(TokenStream $tokenStream): FileNode
    {
        return $this->getFactory()
            ->createParser()
            ->parseAll($tokenStream);
    }

    /**
     * @throws ReaderException
     */
    public function read(NodeInterface $parseTree): ReaderResult
    {
        return $this->getFactory()
            ->createReader()
            ->read($parseTree);
    }

    public function encodeNs(string $namespace): string
    {
        return $this->getFactory()
            ->createMunge()
            ->encodeNs($namespace);
    }
}
