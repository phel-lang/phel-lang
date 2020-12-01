<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Ast\Node;
use Phel\Lang\AbstractType;
use Phel\Lang\Symbol;
use Phel\Lang\Table;

interface AnalyzerInterface
{
    /**
     * @param AbstractType|string|float|int|bool|null $x
     */
    public function analyze($x, NodeEnvironment $env): Node;

    /**
     * @param AbstractType|string|float|int|bool|null $x
     */
    public function analyzeMacro($x, NodeEnvironment $env): Node;

    public function resolve(Symbol $name, NodeEnvironment $env): ?Node;

    public function getNamespace(): string;

    public function setNamespace(string $ns): void;

    public function addUseAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void;

    public function addRequireAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void;

    /**
     * @param Symbol[] $referSymbols
     */
    public function addRefers(string $ns, array $referSymbols, Symbol $nsSymbol): void;

    public function addDefinition(string $ns, Symbol $symbol, Table $meta): void;
}
