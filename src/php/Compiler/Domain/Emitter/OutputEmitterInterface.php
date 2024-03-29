<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\OutputEmitterOptions;
use Phel\Compiler\Domain\Emitter\OutputEmitter\SourceMap\SourceMapState;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

interface OutputEmitterInterface
{
    public function resetIndentLevel(): void;

    public function resetSourceMapState(): void;

    public function getSourceMapState(): SourceMapState;

    public function getOptions(): OutputEmitterOptions;

    public function emitNode(AbstractNode $node): void;

    public function emitLine(string $str = '', ?SourceLocation $sl = null): void;

    public function emitStr(string $str, ?SourceLocation $sl = null): void;

    public function emitArgList(array $nodes, ?SourceLocation $sepLoc, string $sep = ', '): void;

    public function emitContextPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void;

    public function emitContextSuffix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void;

    public function emitFnWrapPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void;

    public function emitPhpVariable(
        Symbol $symbol,
        ?SourceLocation $loc = null,
        bool $asReference = false,
        bool $isVariadic = false,
    ): void;

    public function mungeEncode(string $str): string;

    public function mungeEncodeNs(string $str): string;

    public function emitFnWrapSuffix(?SourceLocation $sl = null): void;

    public function emitLiteral(array|bool|float|int|TypeInterface|string|null $value): void;

    public function increaseIndentLevel(): void;

    public function decreaseIndentLevel(): void;
}
