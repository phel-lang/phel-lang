<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Environment;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Symbol;

interface GlobalEnvironmentInterface
{
    public function getNs(): string;

    public function setNs(string $ns): void;

    public function addDefinition(string $namespace, Symbol $name): void;

    public function hasDefinition(string $namespace, Symbol $name): bool;

    public function getDefinition(string $namespace, Symbol $name): ?PersistentMapInterface;

    public function addRequireAlias(string $inNamespace, Symbol $name, Symbol $fullName): void;

    public function hasRequireAlias(string $inNamespace, Symbol $name): bool;

    public function addUseAlias(string $inNamespace, Symbol $alias, Symbol $fullName): void;

    public function hasUseAlias(string $inNamespace, Symbol $alias): bool;

    public function addRefer(string $inNamespace, Symbol $fnName, Symbol $ns): void;

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode;

    public function resolveAlias(string $alias): ?string;

    public function resolveAsSymbol(Symbol $name, NodeEnvironment $env): ?Symbol;

    public function addInterface(string $namespace, Symbol $name): void;

    public function setAllowPrivateAccess(bool $allowPrivateAccess): void;
}
