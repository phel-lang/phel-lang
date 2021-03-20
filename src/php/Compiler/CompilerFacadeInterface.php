<?php

declare(strict_types=1);

namespace Phel\Compiler;

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
use Phel\Compiler\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Reader\Exceptions\ReaderException;
use Phel\Compiler\Reader\ReaderInterface;

interface CompilerFacadeInterface
{
    public function createLexer(): LexerInterface;

    public function createReader(): ReaderInterface;

    public function createParser(): ParserInterface;

    public function createAnalyzer(): AnalyzerInterface;

    public function createEmitter(bool $enableSourceMaps = true): EmitterInterface;

    public function createOutputEmitter(bool $enableSourceMaps = true): OutputEmitterInterface;

    /**
     * Evaluates a provided Phel code.
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $code, int $startingLine = 1);

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compile(string $filename): string;

    /**
     * @throws LexerValueException
     */
    public function lexString(string $code, string $source = 'string', int $startingLine = 1): TokenStream;

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseNext(TokenStream $tokenStream): ?NodeInterface;

    /**
     * @throws ReaderException
     */
    public function read(NodeInterface $parseTree): ReaderResult;
}
