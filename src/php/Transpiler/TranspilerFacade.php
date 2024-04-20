<?php

declare(strict_types=1);

namespace Phel\Transpiler;

use Gacela\Framework\AbstractFacade;
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

/**
 * @method TranspilerFactory getFactory()
 */
final class TranspilerFacade extends AbstractFacade implements TranspilerFacadeInterface
{
    /**
     * @throws AnalyzerException
     */
    public function analyze(TypeInterface|string|float|int|bool|null $x, NodeEnvironmentInterface $env): AbstractNode
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
    public function eval(
        string $phelCode,
        ?CompileOptions $compileOptions = null,
    ): mixed {
        if (!$compileOptions instanceof CompileOptions) {
            $compileOptions = new CompileOptions();
        }

        return $this->getFactory()
            ->createEvalCompiler()
            ->evalString($phelCode, $compileOptions);
    }

    public function evalForm(
        TypeInterface|string|float|int|bool|null $form,
        ?CompileOptions $compileOptions = null,
    ): mixed {
        if (!$compileOptions instanceof CompileOptions) {
            $compileOptions = new CompileOptions();
        }

        return $this->getFactory()
            ->createEvalCompiler()
            ->evalForm($form, $compileOptions);
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compile(
        string $phelCode,
        ?CompileOptions $compileOptions = null,
    ): EmitterResult {
        if (!$compileOptions instanceof CompileOptions) {
            $compileOptions = new CompileOptions();
        }

        return $this->getFactory()
            ->createCodeCompiler($compileOptions)
            ->compileString($phelCode, $compileOptions);
    }

    /**
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileForm(
        float|bool|int|string|TypeInterface|null $form,
        ?CompileOptions $compileOptions = null,
    ): EmitterResult {
        if (!$compileOptions instanceof CompileOptions) {
            $compileOptions = new CompileOptions();
        }

        return $this->getFactory()
            ->createCodeCompiler($compileOptions)
            ->compileForm($form, $compileOptions);
    }

    /**
     * @throws LexerValueException
     */
    public function lexString(
        string $code,
        string $source = Lexer::DEFAULT_SOURCE,
        bool $withLocation = true,
        int $startingLine = 1,
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
