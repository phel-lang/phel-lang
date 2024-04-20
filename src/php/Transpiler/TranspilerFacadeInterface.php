<?php

declare(strict_types=1);

namespace Phel\Transpiler;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Emitter\EmitterResult;
use Phel\Transpiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Exceptions\CompilerException;
use Phel\Transpiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Transpiler\Domain\Lexer\Lexer;
use Phel\Transpiler\Domain\Lexer\TokenStream;
use Phel\Transpiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Transpiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Transpiler\Domain\Parser\ParserNode\FileNode;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Transpiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Transpiler\Infrastructure\CompileOptions;

interface TranspilerFacadeInterface
{
    /**
     * @throws AnalyzerException
     */
    public function analyze(TypeInterface|string|float|int|bool|null $x, NodeEnvironmentInterface $env): AbstractNode;

    /**
     * Evaluates all expression in the given phel code. Returns the result
     * of the last expression.
     *
     * @param string $phelCode The phel code that should be evaluated
     * @param CompileOptions $compileOptions The compile options
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $phelCode, CompileOptions $compileOptions): mixed;

    /**
     * @param TypeInterface|string|float|int|bool|null $form The phel form to evaluate
     * @param CompileOptions $compileOptions The compile options
     *
     * @throws CompilerException
     *
     * @return mixed The evaluated result
     */
    public function evalForm(TypeInterface|string|float|int|bool|null $form, CompileOptions $compileOptions): mixed;

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
