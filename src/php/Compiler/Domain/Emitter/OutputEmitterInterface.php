<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\Cache\ConstantScope;
use Phel\Compiler\Domain\Emitter\OutputEmitter\OutputEmitterOptions;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapState;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

interface OutputEmitterInterface
{
    public function pushConstantScope(ConstantScope $scope): void;

    public function popConstantScope(): void;

    public function currentConstantScope(): ?ConstantScope;

    public function emitConstantSlotPrefix(AbstractNode $node, ?SourceLocation $loc = null): bool;

    public function emitConstantSlotSuffix(?SourceLocation $loc = null): void;

    public function callSlotFor(AbstractNode $node): ?int;

    public function resetIndentLevel(): void;

    public function resetSourceMapState(): void;

    public function getSourceMapState(): SourceMapState;

    public function getOptions(): OutputEmitterOptions;

    public function emitNode(AbstractNode $node): void;

    /**
     * Emit a node into a temporary buffer and return it as a bare PHP
     * expression string, stripping a leading `return` (with its following
     * whitespace) and a single trailing `;` so the chunk can be spliced
     * into a surrounding expression position.
     */
    public function captureNodeAsExpression(AbstractNode $node): string;

    public function emitLine(string $str = '', ?SourceLocation $sl = null): void;

    public function emitStr(string $str, ?SourceLocation $sl = null): void;

    /**
     * @param list<AbstractNode> $nodes
     */
    public function emitArgList(array $nodes, ?SourceLocation $sepLoc, string $sep = ', '): void;

    public function emitContextPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void;

    public function emitContextSuffix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void;

    /**
     * @param list<string> $byRefLocalNames local names to capture by reference
     */
    public function emitFnWrapPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null, array $byRefLocalNames = []): void;

    public function emitPhpVariable(
        Symbol $symbol,
        ?SourceLocation $loc = null,
        bool $asReference = false,
        bool $isVariadic = false,
    ): void;

    public function mungeEncode(string $str): string;

    public function mungeEncodePhpNs(string $str): string;

    public function mungeEncodeRegistryKey(string $str): string;

    public function emitFnWrapSuffix(?SourceLocation $sl = null): void;

    public function emitLiteral(mixed $value): void;

    public function increaseIndentLevel(): void;

    public function decreaseIndentLevel(): void;

    /**
     * Record that the emitter is now writing into a class body (e.g. the
     * `__invoke` method of a `new class() extends AbstractFn`). Nested
     * `defstruct`/`definterface`/`defexception` forms must then emit via
     * `eval()`, because PHP rejects class declarations nested inside
     * another class's method body.
     */
    public function enterClassScope(): void;

    public function exitClassScope(): void;

    public function isInsideClassScope(): bool;
}
