<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\AbstractFacade;
use Phel\Compiler\Analyzer\AnalyzerInterface;
use Phel\Compiler\Emitter\EmitterInterface;
use Phel\Compiler\Emitter\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Emitter\Exceptions\FileException;
use Phel\Compiler\Emitter\OutputEmitterInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Compiler\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Lexer\LexerInterface;
use Phel\Compiler\Lexer\TokenStream;
use Phel\Compiler\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Parser\ParserInterface;
use Phel\Compiler\Parser\ParserNode\FileNode;
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Compiler\Reader\ReaderInterface;

/**
 * @method CompilerFactory getFactory()
 */
final class CompilerFacade extends AbstractFacade implements CompilerFacadeInterface
{
    public function createLexer(): LexerInterface
    {
        return $this->getFactory()->createLexer();
    }

    public function createReader(): ReaderInterface
    {
        return $this->getFactory()->createReader();
    }

    public function createParser(): ParserInterface
    {
        return $this->getFactory()->createParser();
    }

    public function createAnalyzer(): AnalyzerInterface
    {
        return $this->getFactory()->createAnalyzer();
    }

    public function createEmitter(bool $enableSourceMaps = true): EmitterInterface
    {
        return $this->getFactory()->createEmitter($enableSourceMaps);
    }

    public function createOutputEmitter(bool $enableSourceMaps = true): OutputEmitterInterface
    {
        return $this->getFactory()->createOutputEmitter($enableSourceMaps);
    }

    /**
     * Evaluates a provided Phel code.
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $code, int $startingLine = 1)
    {
        return $this->getFactory()
            ->createEvalCompiler()
            ->eval($code, $startingLine);
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compile(string $filename): string
    {
        return $this->getFactory()
            ->createFileCompiler()
            ->compile($filename);
    }

    /**
     * @throws LexerValueException
     */
    public function lexString(string $code, string $source = 'string', int $startingLine = 1): TokenStream
    {
        return $this->getFactory()
            ->createLexer()
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
     * Reads the next expression from the token stream.
     *
     * If the token stream reaches the end, null is returned.
     *
     * @param NodeInterface $tokenStream The token stream to read
     *
     * @throws ReaderException
     */
    public function read(NodeInterface $parseTree): ReaderResult
    {
        return $this->getFactory()
            ->createReader()
            ->read($parseTree);
    }
}
