<?php

declare(strict_types=1);

namespace Phel\Compiler;

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

interface CompilerFacadeInterface
{
    /**
     * @param TypeInterface|string|float|int|bool|null $x
     *
     * @throws AnalyzerException
     */
    public function analyze($x, NodeEnvironmentInterface $env): AbstractNode;

    /**
     * Evaluates all expression in the given phel code. Returns the result
     * of the last expression.
     *
     * @param string $phelCode The phel code that should be evaluated
     * @param CompileOptions $evalOptions The evaluation options
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $phelCode, CompileOptions $compileOptions);

    /**
     * Compiles the given phel code to PHP code.
     *
     * @param string $phelCode The phel code that should be compiled
     * @param CompileOptions $compileOptions The compilation options
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compile(string $phelCode, CompileOptions $compileOptions): EmitterResult;

    /**
     * @throws LexerValueException
     */
    public function lexString(string $code, string $source = Lexer::DEFAULT_SOURCE, bool $withLocation = true, int $startingLine = 1): TokenStream;

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseNext(TokenStream $tokenStream): ?NodeInterface;

    /**
     * @throws ReaderException
     */
    public function read(NodeInterface $parseTree): ReaderResult;

    /**
     * @throws UnexpectedParserException
     * @throws UnfinishedParserException
     */
    public function parseAll(TokenStream $tokenStream): FileNode;

    public function encodeNs(string $namespace): string;
}
