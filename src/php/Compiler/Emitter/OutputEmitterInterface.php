<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter;

use Phel\Compiler\Ast\Node;
use Phel\Compiler\NodeEnvironmentInterface;
use Phel\Lang\AbstractType;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

interface OutputEmitterInterface
{
    public function emitNodeAsString(Node $node): string;

    public function emitNode(Node $node): void;

    public function emitLine(string $str = '', ?SourceLocation $sl = null): void;

    public function emitStr(string $str, ?SourceLocation $sl = null): void;

    public function emitArgList(array $nodes, ?SourceLocation $sepLoc, string $sep = ', '): void;

    public function emitGlobalBase(string $namespace, Symbol $name): void;

    public function emitGlobalBaseMeta(string $namespace, Symbol $name): void;

    public function emitContextPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void;

    public function emitContextSuffix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void;

    public function emitFnWrapPrefix(NodeEnvironmentInterface $env, ?SourceLocation $sl = null): void;

    public function emitPhpVariable(
        Symbol $symbol,
        ?SourceLocation $loc = null,
        bool $asReference = false,
        bool $isVariadic = false
    ): void ;

    public function mungeEncode(string $str): string;

    public function mungeEncodeNs(string $str): string;

    public function emitFnWrapSuffix(?SourceLocation $sl = null): void;

    /**
     * @param AbstractType|string|float|int|bool|null $value
     */
    public function emitLiteral($value): void;

    public function increaseIndentLevel(): void;

    public function decreaseIndentLevel(): void;
}
