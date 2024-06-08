<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Gacela\Framework\AbstractFacade;
use Phel\Compiler\Application\Lexer;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Evaluator\Exceptions\CompiledCodeIsMalformedException;
use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ParserNode\FileNode;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Compiler\Infrastructure\CompileOptions;
use Phel\Lang\TypeInterface;

/**
 * @method CompilerFactory getFactory()
 */
final class CompilerFacade extends AbstractFacade implements CompilerFacadeInterface
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
