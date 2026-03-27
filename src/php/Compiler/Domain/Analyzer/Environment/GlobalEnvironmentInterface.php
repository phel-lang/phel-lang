<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
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

    /**
     * @return array<string, Symbol>
     */
    public function getRefers(string $namespace): array;

    /**
     * @return array<string, Symbol>
     */
    public function getRequireAliases(string $namespace): array;

    /**
     * @return array<string, Symbol>
     */
    public function getUseAliases(string $namespace): array;

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode;

    public function resolveAlias(string $alias): ?string;

    public function resolveAsSymbol(Symbol $name, NodeEnvironment $env): ?Symbol;

    public function addInterface(string $namespace, Symbol $name): void;

    public function addLevelToAllowPrivateAccess(): void;

    public function removeLevelToAllowPrivateAccess(): void;

    /**
     * Returns all available symbol names that can be resolved in the current namespace.
     * This includes definitions, refers, and aliases.
     *
     * @return array<string> List of available symbol names
     */
    public function getAllDefinitions(): array;

    /**
     * Takes a snapshot of the current environment state.
     * Used by the REPL to rollback on eval errors.
     *
     * @return array{
     *     ns: string,
     *     definitions: array<string, array<string, bool>>,
     *     refers: array<string, array<string, Symbol>>,
     *     requireAliases: array<string, array<string, Symbol>>,
     *     useAliases: array<string, array<string, Symbol>>,
     *     interfaces: array<string, array<string, Symbol>>,
     * }
     */
    public function snapshot(): array;

    /**
     * Restores the environment state from a previously taken snapshot.
     *
     * @param array{
     *     ns: string,
     *     definitions: array<string, array<string, bool>>,
     *     refers: array<string, array<string, Symbol>>,
     *     requireAliases: array<string, array<string, Symbol>>,
     *     useAliases: array<string, array<string, Symbol>>,
     *     interfaces: array<string, array<string, Symbol>>,
     * } $snapshot
     */
    public function restore(array $snapshot): void;
}
