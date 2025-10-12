<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Exceptions\DuplicateDefinitionException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\BuildConstants;
use Phel\Shared\CompilerConstants;
use Phel\Shared\ReplConstants;
use RuntimeException;

use function array_key_exists;
use function dirname;

final class GlobalEnvironment implements GlobalEnvironmentInterface
{
    private string $ns = 'user';

    /** @var array<string, array<string, bool>> */
    private array $definitions = [];

    /** @var array<string, array<string, Symbol>> */
    private array $refers = [];

    /** @var array<string, array<string, Symbol>> */
    private array $requireAliases = [];

    /** @var array<string, array<string, Symbol>> */
    private array $useAliases = [];

    /** @var array<string, array<string, Symbol>> */
    private array $interfaces = [];

    private int $allowPrivateAccessCounter = 0;

    public function __construct()
    {
        $this->addInternalBuildModeDefinition();
    }

    public function getNs(): string
    {
        return $this->ns;
    }

    public function setNs(string $ns): void
    {
        $this->ns = $ns;
    }

    public function addDefinition(string $namespace, Symbol $name): void
    {
        $this->initializeNamespace($namespace);

        if ($this->shouldThrowOnDuplicateDefinition($namespace, $name)) {
            throw DuplicateDefinitionException::forSymbol($namespace, $name);
        }

        $this->definitions[$namespace][$name->getName()] = true;
    }

    public function hasDefinition(string $namespace, Symbol $name): bool
    {
        return (
            isset($this->definitions[$namespace][$name->getName()])
            || Phel::hasDefinition(
                $this->mungeEncodeNs($namespace),
                $name->getName(),
            )
        );
    }

    public function getDefinition(string $namespace, Symbol $name): ?PersistentMapInterface
    {
        if ($this->hasDefinition($namespace, $name)) {
            return Phel::getDefinitionMetaData(
                $this->mungeEncodeNs($namespace),
                $name->getName(),
            ) ?? Phel::map();
        }

        return null;
    }

    /**
     * @param string $inNamespace The namespace in which the alias exist
     * @param Symbol $name        The alias name
     * @param Symbol $fullName    The namespace that will be resolved
     */
    public function addRequireAlias(string $inNamespace, Symbol $name, Symbol $fullName): void
    {
        $this->requireAliases[$inNamespace][$name->getName()] = $fullName;
    }

    /**
     * @param string $inNamespace The namespace in which the alias should exist
     * @param Symbol $name        The alias name
     */
    public function hasRequireAlias(string $inNamespace, Symbol $name): bool
    {
        return isset($this->requireAliases[$inNamespace][$name->getName()]);
    }

    /**
     * @param string $inNamespace The namespace in which the alias exist
     * @param Symbol $alias       The alias name
     * @param Symbol $fullName    The namespace that will be resolved
     */
    public function addUseAlias(string $inNamespace, Symbol $alias, Symbol $fullName): void
    {
        $this->useAliases[$inNamespace][$alias->getName()] = $fullName;
    }

    /**
     * @param string $inNamespace The namespace in which the alias should exist
     * @param Symbol $alias       The alias name
     */
    public function hasUseAlias(string $inNamespace, Symbol $alias): bool
    {
        return isset($this->useAliases[$inNamespace][$alias->getName()]);
    }

    public function addRefer(string $inNamespace, Symbol $fnName, Symbol $ns): void
    {
        $this->refers[$inNamespace][$fnName->getName()] = $ns;
    }

    public function resolveAsSymbol(Symbol $name, NodeEnvironment $env): ?Symbol
    {
        $node = $this->resolve($name, $env);
        if ($node instanceof GlobalVarNode) {
            return Symbol::createForNamespace($node->getNamespace(), $node->getName()->getName());
        }

        return null;
    }

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode
    {
        $strName = $name->getName();

        if ($strName === '__DIR__') {
            return new LiteralNode(
                $env,
                $this->resolveMagicDir($name->getStartLocation()),
            );
        }

        if ($strName === '__FILE__') {
            return new LiteralNode(
                $env,
                $this->resolveMagicFile($name->getStartLocation()),
            );
        }

        if ($strName[0] === '\\') {
            return new PhpClassNameNode($env, $name, $name->getStartLocation());
        }

        if (isset($this->useAliases[$this->ns][$strName])) {
            $alias = $this->useAliases[$this->ns][$strName];
            $alias->copyLocationFrom($name);
            return new PhpClassNameNode($env, $alias, $name->getStartLocation());
        }

        return ($name->getNamespace() !== null)
            ? $this->resolveWithAlias($name, $env)
            : $this->resolveWithoutAlias($name, $env);
    }

    public function resolveAlias(string $alias): ?string
    {
        if (isset($this->requireAliases[$this->ns][$alias])) {
            return $this->requireAliases[$this->ns][$alias]->getName();
        }

        return null;
    }

    public function addLevelToAllowPrivateAccess(): void
    {
        ++$this->allowPrivateAccessCounter;
    }

    public function removeLevelToAllowPrivateAccess(): void
    {
        --$this->allowPrivateAccessCounter;
    }

    public function addInterface(string $namespace, Symbol $name): void
    {
        if (!array_key_exists($namespace, $this->interfaces)) {
            $this->interfaces[$namespace] = [];
        }

        $this->interfaces[$namespace][$name->getName()] = $name;
    }

    private function initializeNamespace(string $namespace): void
    {
        if (!array_key_exists($namespace, $this->definitions)) {
            $this->definitions[$namespace] = [];
        }
    }

    private function shouldThrowOnDuplicateDefinition(string $namespace, Symbol $name): bool
    {
        if (Phel::getDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, BuildConstants::BUILD_MODE)
            || Phel::getDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, ReplConstants::REPL_MODE)
            || $namespace === CompilerConstants::PHEL_CORE_NAMESPACE
        ) {
            return false;
        }

        $symbolName = $name->getName();

        if (!isset($this->definitions[$namespace][$symbolName])) {
            return false;
        }

        return Phel::hasDefinition($namespace, $symbolName);
    }

    private function addInternalBuildModeDefinition(): void
    {
        $symbol = Symbol::create(BuildConstants::BUILD_MODE);
        $meta = Phel::map(
            Keyword::create('doc'),
            'Set to true when a file is being built/compiled, false otherwise.',
        );
        Phel::addDefinition(
            CompilerConstants::PHEL_CORE_NAMESPACE,
            $symbol->getName(),
            false,
            $meta,
        );
        $this->addDefinition(CompilerConstants::PHEL_CORE_NAMESPACE, $symbol);
    }

    private function resolveMagicFile(?SourceLocation $sl): ?string
    {
        return $this->resolveMagicSourceString($sl)
            ?? $this->resolveRealpath($sl);
    }

    private function resolveMagicDir(?SourceLocation $sl): ?string
    {
        return $this->resolveMagicSourceString($sl)
            ?? $this->resolveRealpathDirname($sl);
    }

    private function resolveMagicSourceString(?SourceLocation $sl): ?string
    {
        return ($sl instanceof SourceLocation && $sl->getFile() === 'string') ? '' : null;
    }

    private function resolveRealpath(?SourceLocation $sl): ?string
    {
        if (!$sl instanceof SourceLocation) {
            return null;
        }

        $realpath = realpath($sl->getFile());

        return $realpath === false ? null : $realpath;
    }

    private function resolveRealpathDirname(?SourceLocation $sl): ?string
    {
        if (!$sl instanceof SourceLocation) {
            return null;
        }

        $realpath = realpath(dirname($sl->getFile()));

        return $realpath === false ? null : $realpath;
    }

    private function resolveWithAlias(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode
    {
        $alias = $name->getNamespace();
        if ($alias === null) {
            throw new RuntimeException('resolveWithAlias called with a Symbol without namespace');
        }

        $finalName = Symbol::create($name->getName());
        $ns = $this->resolveAlias($alias) ?? $alias;

        return $this->resolveInterfaceOrDefinition($finalName, $env, $ns);
    }

    private function resolveWithoutAlias(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode
    {
        $currentNs = $this->ns;
        if (isset($this->refers[$this->ns][$name->getName()])) {
            $currentNs = $this->refers[$this->ns][$name->getName()]->getName();

            return $this->resolveInterfaceOrDefinition($name, $env, $currentNs)
                ?? $this->resolveInterfaceOrDefinition($name, $env, CompilerConstants::PHEL_CORE_NAMESPACE);
        }

        return $this->resolveInterfaceOrDefinitionForCurrentNs($name, $env, $currentNs)
            ?? $this->resolveInterfaceOrDefinition($name, $env, CompilerConstants::PHEL_CORE_NAMESPACE);
    }

    /**
     * It also includes private definitions from the current namespace.
     */
    private function resolveInterfaceOrDefinitionForCurrentNs(
        Symbol $name,
        NodeEnvironmentInterface $env,
        string $ns,
    ): ?AbstractNode {
        if (isset($this->interfaces[$ns][$name->getName()])) {
            return new PhpClassNameNode(
                $env,
                Symbol::createForNamespace($ns, $name->getName()),
                $name->getStartLocation(),
            );
        }

        $def = $this->getDefinition($ns, $name);
        if ($def instanceof PersistentMapInterface) {
            return new GlobalVarNode($env, $ns, $name, $def, $name->getStartLocation());
        }

        return null;
    }

    /**
     * It ignores private definitions (if they're not allowed) from the namespace.
     */
    private function resolveInterfaceOrDefinition(
        Symbol $name,
        NodeEnvironmentInterface $env,
        string $ns,
    ): ?AbstractNode {
        if (isset($this->interfaces[$ns][$name->getName()])) {
            return new PhpClassNameNode(
                $env,
                Symbol::createForNamespace($ns, $name->getName()),
                $name->getStartLocation(),
            );
        }

        $def = $this->getDefinition($ns, $name);
        if (!$def instanceof PersistentMapInterface) {
            return null;
        }

        if (!$this->isPrivateDefinitionAllowed($def)) {
            return null;
        }

        return new GlobalVarNode($env, $ns, $name, $def, $name->getStartLocation());
    }

    private function isPrivateDefinitionAllowed(PersistentMapInterface $meta): bool
    {
        return $this->allowPrivateAccessCounter > 0
            || !$meta[Keyword::create('private')];
    }

    private function mungeEncodeNs(string $ns): string
    {
        return str_replace('-', '_', $ns);
    }
}
