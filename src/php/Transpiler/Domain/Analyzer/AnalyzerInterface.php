<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer;

use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Transpiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Transpiler\Domain\Analyzer\Exceptions\AnalyzerException;

interface AnalyzerInterface
{
    /**
     * @throws AnalyzerException
     */
    public function analyze(TypeInterface|array|string|float|int|bool|null $x, NodeEnvironmentInterface $env): AbstractNode;

    public function analyzeMacro(TypeInterface|array|string|float|int|bool|null $x, NodeEnvironmentInterface $env): AbstractNode;

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode;

    public function getNamespace(): string;

    public function setNamespace(string $ns): void;

    public function addUseAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void;

    public function addRequireAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void;

    /**
     * @param list<Symbol> $referSymbols
     */
    public function addRefers(string $ns, array $referSymbols, Symbol $nsSymbol): void;

    public function addDefinition(string $ns, Symbol $symbol): void;

    public function addInterface(string $ns, Symbol $name): void;
}
