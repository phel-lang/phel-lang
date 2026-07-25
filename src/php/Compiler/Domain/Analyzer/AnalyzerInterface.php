<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNodeInterface;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Symbol;

interface AnalyzerInterface
{
    /**
     * @throws AnalyzerException
     */
    public function analyze(mixed $x, NodeEnvironmentInterface $env): AbstractNode;

    public function analyzeMacro(mixed $x, NodeEnvironmentInterface $env): AbstractNode;

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode;

    public function getNamespace(): string;

    public function setNamespace(string $ns): void;

    public function addUseAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void;

    public function addRequireAlias(string $ns, Symbol $alias, Symbol $nsSymbol): void;

    /**
     * @param list<Symbol> $referSymbols
     */
    public function addRefers(string $ns, array $referSymbols, Symbol $nsSymbol): void;

    public function addDefinition(string $ns, Symbol $symbol, bool $allowRedefinition = false): void;

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    public function setCompileTimeMeta(string $ns, Symbol $symbol, PersistentMapInterface $meta): void;

    public function setDefFnNode(string $ns, Symbol $symbol, FnNodeInterface $node): void;

    public function getDefFnNode(string $ns, Symbol $symbol): ?FnNodeInterface;

    public function setOptimizationLevel(int $level): void;

    public function getOptimizationLevel(): int;

    public function addInterface(string $ns, Symbol $name): void;

    /**
     * Record the method names declared by a `definterface`, so an inline
     * implementation can be validated without the generated PHP interface
     * having been evaluated (compile-only flows never evaluate).
     *
     * @param list<string> $methodNames
     */
    public function setInterfaceMethods(string $ns, Symbol $name, array $methodNames): void;

    /**
     * @return list<string>|null null when the interface was never analyzed here
     */
    public function getInterfaceMethods(string $ns, Symbol $name): ?array;

    /**
     * Returns all available symbol names that can be resolved in the current namespace.
     *
     * @return array<string> List of available symbol names
     */
    public function getAvailableSymbols(): array;
}
