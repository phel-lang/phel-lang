<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Environment;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Analyzer\Ast\PhpClassNameNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\TypeFactory;
use Phel\Lang\TypeInterface;
use RuntimeException;

final class GlobalEnvironment implements GlobalEnvironmentInterface
{
    private string $ns = 'user';

    /** @var array<string, array<string, PersistentMapInterface>> */
    private array $definitions = [];

    /** @var array<string, array<string, Symbol>> */
    private array $refers = [];

    /** @var array<string, array<string, Symbol>> */
    private array $requireAliases = [];

    /** @var array<string, array<string, Symbol>> */
    private array $useAliases = [];

    /** @var array<string, array<string, Symbol>> */
    private array $interfaces = [];

    private bool $allowPrivateAccess = false;

    public function __construct()
    {
        $this->addInternalDefinition('phel\core', Symbol::create('*compile-mode*'), false);
    }

    public function getNs(): string
    {
        return $this->ns;
    }

    public function setNs(string $ns): void
    {
        $this->ns = $ns;
    }

    public function addDefinition(string $namespace, Symbol $name, PersistentMapInterface $meta): void
    {
        if (!array_key_exists($namespace, $this->definitions)) {
            $this->definitions[$namespace] = [];
        }

        $this->definitions[$namespace][$name->getName()] = $meta;
    }

    public function hasDefinition(string $namespace, Symbol $name): bool
    {
        return (isset($this->definitions[$namespace][$name->getName()]));
    }

    public function getDefinition(string $namespace, Symbol $name): ?PersistentMapInterface
    {
        if ($this->hasDefinition($namespace, $name)) {
            return $this->definitions[$namespace][$name->getName()];
        }

        return null;
    }

    /**
     * Adds an require alias.
     *
     * @param string $inNamespace The namespace in which the alias exist
     * @param Symbol $name The alias name
     * @param Symbol $fullName The namespace that will be resolve
     */
    public function addRequireAlias(string $inNamespace, Symbol $name, Symbol $fullName): void
    {
        $this->requireAliases[$inNamespace][$name->getName()] = $fullName;
    }

    /**
     * Checks if an require alias exists.
     *
     * @param string $inNamespace The namespace in which the alias should exist
     * @param Symbol $name The alias name
     */
    public function hasRequireAlias(string $inNamespace, Symbol $name): bool
    {
        return isset($this->requireAliases[$inNamespace][$name->getName()]);
    }

    /**
     * Adds an use alias.
     *
     * @param string $inNamespace The namespace in which the alias exist
     * @param Symbol $alias The alias name
     * @param Symbol $fullName The namespace that will be resolve
     */
    public function addUseAlias(string $inNamespace, Symbol $alias, Symbol $fullName): void
    {
        $this->useAliases[$inNamespace][$alias->getName()] = $fullName;
    }

    /**
     * Checks if an use alias exists.
     *
     * @param string $inNamespace The namespace in which the alias should exist
     * @param Symbol $alias The alias name
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
                $this->resolveMagicDir($name->getStartLocation())
            );
        }

        if ($strName === '__FILE__') {
            return new LiteralNode(
                $env,
                $this->resolveMagicFile($name->getStartLocation())
            );
        }

        if ($strName[0] === '\\') {
            return new PhpClassNameNode($env, $name, $name->getStartLocation());
        }

        if (isset($this->useAliases[$this->ns][$strName])) {
            /** @var Symbol $alias */
            $alias = $this->useAliases[$this->ns][$strName];
            $alias->copyLocationFrom($name);
            return new PhpClassNameNode($env, $alias, $name->getStartLocation());
        }

        if ($name->getNamespace() !== null) {
            return $this->resolveWithAlias($name, $env);
        }

        return $this->resolveWithoutAlias($name, $env);
    }

    private function resolveMagicFile(?SourceLocation $sl): ?string
    {
        if ($sl && $sl->getFile() === 'string') {
            return 'string';
        }

        if ($sl) {
            return realpath($sl->getFile());
        }

        return null;
    }

    private function resolveMagicDir(?SourceLocation $sl): ?string
    {
        if ($sl && $sl->getFile() === 'string') {
            return 'string';
        }

        if ($sl) {
            return realpath(dirname($sl->getFile()));
        }

        return null;
    }

    public function resolveAlias(string $alias): ?string
    {
        if (isset($this->requireAliases[$this->ns][$alias])) {
            return $this->requireAliases[$this->ns][$alias]->getName();
        }

        return null;
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
        $ns = $this->getNs();
        if (isset($this->refers[$this->ns][$name->getName()])) {
            $ns = $this->refers[$this->ns][$name->getName()]->getName();
        }

        return $this->resolveInterfaceOrDefinitionForCurrentNs($name, $env, $ns)
            ?? $this->resolveInterfaceOrDefinition($name, $env, 'phel\core');
    }

    /**
     * It also includes private definitions from the current namespace.
     */
    private function resolveInterfaceOrDefinitionForCurrentNs(Symbol $name, NodeEnvironmentInterface $env, string $ns): ?AbstractNode
    {
        if (isset($this->interfaces[$ns][$name->getName()])) {
            return new PhpClassNameNode($env, Symbol::createForNamespace($ns, $name->getName()), $name->getStartLocation());
        }

        $def = $this->getDefinition($ns, $name);
        if ($def) {
            return new GlobalVarNode($env, $ns, $name, $def, $name->getStartLocation());
        }

        return null;
    }

    /**
     * It ignores private definitions (if they're not allowed) from the namespace.
     */
    private function resolveInterfaceOrDefinition(Symbol $name, NodeEnvironmentInterface $env, string $ns): ?AbstractNode
    {
        if (isset($this->interfaces[$ns][$name->getName()])) {
            return new PhpClassNameNode($env, Symbol::createForNamespace($ns, $name->getName()), $name->getStartLocation());
        }

        $def = $this->getDefinition($ns, $name);
        if ($def && $this->isPrivateDefinitionAllowed($def)) {
            return new GlobalVarNode($env, $ns, $name, $def, $name->getStartLocation());
        }

        return null;
    }

    private function isPrivateDefinitionAllowed(PersistentMapInterface $meta): bool
    {
        return $this->allowPrivateAccess || !$meta[Keyword::create('private')] === true;
    }

    public function setAllowPrivateAccess(bool $allowPrivateAccess): void
    {
        $this->allowPrivateAccess = $allowPrivateAccess;
    }

    /**
     * @param TypeInterface|string|float|int|bool|null $value The initial value
     */
    private function addInternalDefinition(string $namespace, Symbol $symbol, $value): void
    {
        $GLOBALS['__phel'][$namespace][$symbol->getName()] = $value;
        $this->addDefinition($namespace, $symbol, TypeFactory::getInstance()->persistentMapFromKVs(
            Keyword::create('doc'),
            'Set to true when a file is compiled, false otherwise',
        ));
    }

    public function addInterface(string $namespace, Symbol $name): void
    {
        if (!array_key_exists($namespace, $this->interfaces)) {
            $this->interfaces[$namespace] = [];
        }

        $this->interfaces[$namespace][$name->getName()] = $name;
    }
}
