<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel;
use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Exceptions\DuplicateDefinitionException;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\BuildConstants;
use Phel\Shared\CompilerConstants;
use Phel\Shared\ReplConstants;

use function array_key_exists;

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

    private readonly SymbolResolver $symbolResolver;

    public function __construct()
    {
        $this->symbolResolver = new SymbolResolver(
            $this,
            new MagicConstantResolver(),
            BackslashSeparatorDeprecator::getInstance(),
        );
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

    public function getRefers(string $namespace): array
    {
        return $this->refers[$namespace] ?? [];
    }

    public function getRequireAliases(string $namespace): array
    {
        return $this->requireAliases[$namespace] ?? [];
    }

    public function getUseAliases(string $namespace): array
    {
        return $this->useAliases[$namespace] ?? [];
    }

    /**
     * @return array<string, Symbol>
     */
    public function getInterfaces(string $namespace): array
    {
        return $this->interfaces[$namespace] ?? [];
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
        return $this->symbolResolver->resolve($name, $env);
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

    public function isPrivateAccessAllowed(): bool
    {
        return $this->allowPrivateAccessCounter > 0;
    }

    public function addInterface(string $namespace, Symbol $name): void
    {
        if (!array_key_exists($namespace, $this->interfaces)) {
            $this->interfaces[$namespace] = [];
        }

        $this->interfaces[$namespace][$name->getName()] = $name;
    }

    public function snapshot(): array
    {
        return [
            'ns' => $this->ns,
            'definitions' => $this->definitions,
            'refers' => $this->refers,
            'requireAliases' => $this->requireAliases,
            'useAliases' => $this->useAliases,
            'interfaces' => $this->interfaces,
        ];
    }

    public function restore(array $snapshot): void
    {
        $this->ns = $snapshot['ns'];
        $this->definitions = $snapshot['definitions'];
        $this->refers = $snapshot['refers'];
        $this->requireAliases = $snapshot['requireAliases'];
        $this->useAliases = $snapshot['useAliases'];
        $this->interfaces = $snapshot['interfaces'];
    }

    public function getAllDefinitions(): array
    {
        $symbols = [];

        $sources = [
            $this->definitions[$this->ns] ?? [],
            $this->definitions[CompilerConstants::PHEL_CORE_NAMESPACE] ?? [],
            $this->refers[$this->ns] ?? [],
            $this->requireAliases[$this->ns] ?? [],
            $this->useAliases[$this->ns] ?? [],
            $this->interfaces[$this->ns] ?? [],
            $this->interfaces[CompilerConstants::PHEL_CORE_NAMESPACE] ?? [],
            Phel::getDefinitionInNamespace($this->mungeEncodeNs($this->ns)),
            Phel::getDefinitionInNamespace($this->mungeEncodeNs(CompilerConstants::PHEL_CORE_NAMESPACE)),
        ];

        foreach ($sources as $source) {
            foreach (array_keys($source) as $name) {
                $symbols[$name] = true;
            }
        }

        return array_keys($symbols);
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

    private function mungeEncodeNs(string $ns): string
    {
        return str_replace('-', '_', $ns);
    }
}
