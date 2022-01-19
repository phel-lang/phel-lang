<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;

interface AnalyzerInterface
{
    /**
     * @param TypeInterface|string|float|int|bool|null $x
     *
     * @throws AnalyzerException
     */
    public function analyze($x, NodeEnvironmentInterface $env): AbstractNode;

    /**
     * @param TypeInterface|string|float|int|bool|null $x
     */
    public function analyzeMacro($x, NodeEnvironmentInterface $env): AbstractNode;

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode;

    public function getNamespace(): string;

    public function setNamespace(string $ns): void;

    public function addUseAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void;

    public function addRequireAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void;

    /**
     * @param Symbol[] $referSymbols
     */
    public function addRefers(string $ns, array $referSymbols, Symbol $nsSymbol): void;

    public function addDefinition(string $ns, Symbol $symbol): void;

    public function addInterface(string $ns, Symbol $name): void;
}
