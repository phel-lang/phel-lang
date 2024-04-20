<?php

declare(strict_types=1);

namespace Phel\Transpiler;

use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Transpiler\Domain\Emitter\EmitterResult;
use Phel\Transpiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Transpiler\Domain\Evaluator\Exceptions\TrarnspiledCodeIsMalformedException;
use Phel\Transpiler\Domain\Exceptions\TranspilerException;
use Phel\Transpiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Transpiler\Domain\Lexer\Lexer;
use Phel\Transpiler\Domain\Lexer\TokenStream;
use Phel\Transpiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Transpiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Transpiler\Domain\Parser\ParserNode\FileNode;
use Phel\Transpiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Transpiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Transpiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Transpiler\Infrastructure\TranspileOptions;

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
     * @param TranspileOptions $options The compile options
     *
     *@throws TranspilerException|UnfinishedParserException
     *
     *@return mixed The result of the executed code
     */
    public function eval(string $phelCode, TranspileOptions $options): mixed;

    /**
     * @param TypeInterface|string|float|int|bool|null $form The phel form to evaluate
     * @param TranspileOptions $options The compile options
     *
     *@throws TranspilerException
     *
     *@return mixed The evaluated result
     */
    public function evalForm(TypeInterface|string|float|int|bool|null $form, TranspileOptions $options): mixed;

    /**
     * Transpiles the given phel code to PHP code.
     *
     * @param string $phelCode The phel code that should be compiled
     * @param TranspileOptions $options The transpilation options
     *
     * @throws TranspilerException
     * @throws TrarnspiledCodeIsMalformedException
     * @throws FileException
     */
    public function transpile(string $phelCode, TranspileOptions $options): EmitterResult;

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
