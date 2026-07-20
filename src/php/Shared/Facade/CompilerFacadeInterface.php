<?php

declare(strict_types=1);

namespace Phel\Shared\Facade;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Emitter\EmitterResult;
use Phel\Compiler\Domain\Lexer\Exceptions\LexerValueException;
use Phel\Compiler\Domain\Lexer\TokenStream;
use Phel\Compiler\Domain\Parser\Exceptions\UnexpectedParserException;
use Phel\Compiler\Domain\Parser\Exceptions\UnfinishedParserException;
use Phel\Compiler\Domain\Parser\ReadModel\ReaderResult;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\TypeInterface;
use Phel\Shared\CompileOptions;
use Phel\Shared\CompilerConstants;
use Phel\Shared\Exceptions\CompiledCodeIsMalformedException;
use Phel\Shared\Exceptions\CompilerException;
use Phel\Shared\Exceptions\FileException;
use Phel\Shared\Parser\Node\FileNode;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * @phpstan-type SerializedSymbol array{ns: ?string, name: string}
 * @phpstan-type SerializedNamespaceEnvironment array{
 *     refers: array<string, SerializedSymbol>,
 *     require_aliases: array<string, SerializedSymbol>,
 *     use_aliases: array<string, SerializedSymbol>,
 * }
 */
interface CompilerFacadeInterface
{
    /**
     * @throws AnalyzerException
     */
    public function analyze(TypeInterface|string|float|int|bool|null $x, NodeEnvironmentInterface $env): AbstractNode;

    /**
     * Evaluates all expression in the given phel code. Returns the result
     * of the last expression.
     *
     * @param string         $phelCode       The phel code that should be evaluated
     * @param CompileOptions $compileOptions The compile options
     *
     * @throws CompilerException|UnfinishedParserException
     *
     * @return mixed The result of the executed code
     */
    public function eval(string $phelCode, CompileOptions $compileOptions): mixed;

    /**
     * Evaluates a Phel form. Non-Phel objects (e.g. closures) are returned
     * as-is, matching Clojure's self-evaluating semantics.
     *
     * @param mixed          $form           The phel form to evaluate
     * @param CompileOptions $compileOptions The compile options
     *
     * @throws CompilerException
     *
     * @return mixed The evaluated result
     */
    public function evalForm(mixed $form, CompileOptions $compileOptions): mixed;

    /**
     * Compiles the given phel code to PHP code.
     *
     * @param string         $phelCode       The phel code that should be compiled
     * @param CompileOptions $compileOptions The compilation options
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compile(string $phelCode, CompileOptions $compileOptions): EmitterResult;

    /**
     * Compiles the given phel code to PHP code suitable for caching.
     * Uses statement emit mode (no require_once statements for dependencies).
     *
     * @param string         $phelCode       The phel code that should be compiled
     * @param CompileOptions $compileOptions The compilation options
     *
     * @throws CompilerException
     * @throws CompiledCodeIsMalformedException
     * @throws FileException
     */
    public function compileForCache(string $phelCode, CompileOptions $compileOptions): EmitterResult;

    /**
     * @throws LexerValueException
     */
    public function lexString(string $code, string $source = CompilerConstants::DEFAULT_SOURCE, bool $withLocation = true, int $startingLine = 1): TokenStream;

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

    public function hasBalancedParentheses(string $code): bool;

    /**
     * Ensures the GlobalEnvironment is initialized.
     * If already initialized, returns without action.
     * If not, initializes a new one (which clears the Phel registry).
     */
    public function initializeGlobalEnvironment(): void;

    /**
     * Resets the GlobalEnvironment to an uninitialized state.
     */
    public function resetGlobalEnvironment(): void;

    /**
     * True when a GlobalEnvironment singleton has been bootstrapped.
     * Cross-module callers use this to decide whether to snapshot the
     * env before a transient operation that might replace it.
     */
    public function isGlobalEnvironmentInitialized(): bool;

    /**
     * Returns the current GlobalEnvironment, auto-creating a throwaway one
     * if none has been initialized. Use this when downstream code only
     * reads state (e.g. `getNs()`, definition lookups).
     */
    public function getGlobalEnvironment(): GlobalEnvironmentInterface;

    /**
     * Clears the Phel registry and installs a fresh GlobalEnvironment.
     * Used by tooling that needs to load namespaces in isolation and then
     * restore the previous env via {@see self::setGlobalEnvironment()}.
     */
    public function initializeNewGlobalEnvironment(): GlobalEnvironmentInterface;

    /**
     * Replace the GlobalEnvironment singleton with a previously captured
     * instance. Pairs with {@see self::initializeNewGlobalEnvironment()}
     * for transient snapshot/restore workflows.
     */
    public function setGlobalEnvironment(GlobalEnvironmentInterface $env): void;

    /**
     * Toggle line-by-line PHP execution tracing for compiled Phel code.
     * Used by `phel run --debug=<filter>`; idempotent on repeated enables.
     */
    public function enableDebugLineTap(?string $phelFileFilter = null, string $logPath = './phel-debug.log'): void;

    public function disableDebugLineTap(): void;

    /**
     * Expands a macro form once. Returns the expanded Phel form,
     * or the original form unchanged if it is not a macro call.
     */
    public function macroexpand1(TypeInterface|string|float|int|bool|null $form): TypeInterface|string|float|int|bool|null;

    /**
     * Repeatedly expands a macro form until it is no longer a macro call.
     * Returns the fully expanded Phel form.
     */
    public function macroexpand(TypeInterface|string|float|int|bool|null $form): TypeInterface|string|float|int|bool|null;

    /**
     * Extracts the current GlobalEnvironment state for a namespace
     * in a serializable plain-array format.
     *
     * @return SerializedNamespaceEnvironment
     */
    public function getNamespaceEnvironmentData(string $namespace): array;

    /**
     * Restores GlobalEnvironment state for a namespace from
     * previously serialized environment data.
     *
     * @param SerializedNamespaceEnvironment $envData
     */
    public function restoreNamespaceEnvironmentData(string $namespace, array $envData): void;
}
