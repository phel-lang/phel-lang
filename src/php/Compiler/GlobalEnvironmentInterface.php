<?php

declare(strict_types=1);

namespace Phel\Compiler;

use Phel\Compiler\Ast\Node;
use Phel\Lang\Symbol;
use Phel\Lang\Table;

interface GlobalEnvironmentInterface
{
    public function getNs(): string;

    public function setNs(string $ns): void;

    public function addDefinition(string $namespace, Symbol $name, Table $meta): void;

    public function hasDefinition(string $namespace, Symbol $name): bool;

    public function getDefinition(string $namespace, Symbol $name): ?Table;

    public function addRequireAlias(string $inNamespace, Symbol $name, Symbol $fullName): void;

    public function hasRequireAlias(string $inNamespace, Symbol $name): bool;

    public function addUseAlias(string $inNamespace, Symbol $alias, Symbol $fullName): void;

    public function hasUseAlias(string $inNamespace, Symbol $alias): bool;

    public function addRefer(string $inNamespace, Symbol $fnName, Symbol $ns): void;

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?Node;

    public function setAllowPrivateAccess(bool $allowPrivateAccess): void;
}
