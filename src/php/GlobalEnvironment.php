<?php

declare(strict_types=1);

namespace Phel;

use Phel\Ast\GlobalVarNode;
use Phel\Ast\LiteralNode;
use Phel\Ast\Node;
use Phel\Ast\PhpClassNameNode;
use Phel\Lang\Keyword;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Lang\Table;

final class GlobalEnvironment
{
    private string $ns = 'user';

    private array $definitions = [];

    private array $requireAliases = [];

    private array $useAliases = [];

    private bool $allowPrivateAccess = false;

    public function getNs(): string
    {
        return $this->ns;
    }

    public function setNs(string $ns): void
    {
        $this->ns = $ns;
    }

    public function addDefinition(string $namespace, Symbol $name, Table $meta): void
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

    public function getDefinition(string $namespace, Symbol $name): ?Table
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
     * @param Symbol The alias name
     * @param Symbol The namespace that will be resolve.
     */
    public function addRequireAlias(string $inNamespace, Symbol $name, Symbol $fullName): void
    {
        $this->requireAliases[$inNamespace][$name->getName()] = $fullName;
    }

    /**
     * Checks if an require alias exists.
     *
     * @param string $inNamespace The namespace in which the alias should exist
     * @param Symbol The alias name
     *
     * @return boolean
     */
    public function hasRequireAlias(string $inNamespace, Symbol $name): bool
    {
        return isset($this->requireAliases[$inNamespace][$name->getName()]);
    }

    /**
     * Adds an use alias.
     *
     * @param string $inNamespace The namespace in which the alias exist
     * @param Symbol The alias name
     * @param Symbol The namespace that will be resolve.
     */
    public function addUseAlias(string $inNamespace, Symbol $alias, Symbol $fullName): void
    {
        $this->useAliases[$inNamespace][$alias->getName()] = $fullName;
    }

    /**
     * Checks if an use alias exists.
     *
     * @param string $inNamespace The namespace in which the alias should exist
     * @param Symbol The alias name
     *
     * @return boolean
     */
    public function hasUseAlias(string $inNamespace, Symbol $alias): bool
    {
        return isset($this->useAliases[$inNamespace][$alias->getName()]);
    }

    public function resolve(Symbol $name, NodeEnvironment $env): ?Node
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

        $pos = strpos($strName, '/');

        if ($pos !== false && $pos > 0) {
            return $this->resolveWithAlias($strName, $pos, $env, $name);
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

    private function resolveWithAlias(string $strName, int $pos, NodeEnvironment $env, Symbol $name): ?GlobalVarNode
    {
        $alias = substr($strName, 0, $pos);

        if (!isset($this->requireAliases[$this->ns][$alias])) {
            return null;
        }

        $namespace = $this->requireAliases[$this->ns][$alias];
        $finalName = new Symbol(substr($strName, $pos + 1));

        $def = $this->getDefinition($namespace->getName(), $finalName);
        if ($def && ($this->allowPrivateAccess || !$this->isDefinitionPrivate($def))) {
            return new GlobalVarNode($env, $namespace->getName(), $finalName, $def, $name->getStartLocation());
        }

        return null;
    }

    private function resolveWithoutAlias(Symbol $name, NodeEnvironment $env): ?GlobalVarNode
    {
        // Try to resolve in current namespace
        $def = $this->getDefinition($this->getNs(), $name);
        if ($def) {
            return new GlobalVarNode($env, $this->getNs(), $name, $def, $name->getStartLocation());
        }

        // Try to resolve in phel.core namespace
        $ns = 'phel\core';
        $def = $this->getDefinition($ns, $name);
        if ($def && ($this->allowPrivateAccess || !$this->isDefinitionPrivate($def))) {
            return new GlobalVarNode($env, $ns, $name, $def, $name->getStartLocation());
        }

        return null;
    }

    private function isDefinitionPrivate(Table $meta)
    {
        return $meta[new Keyword('private')] === true;
    }

    public function setAllowPrivateAccess(bool $allowPrivateAccess)
    {
        $this->allowPrivateAccess = $allowPrivateAccess;
    }
}
