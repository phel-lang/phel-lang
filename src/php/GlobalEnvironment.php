<?php

namespace Phel;

use Phel\Ast\GlobalVarNode;
use Phel\Ast\PhpClassNameNode;
use Phel\Lang\Symbol;
use Phel\Lang\Table;

class GlobalEnvironment {

    protected $ns = 'user';

    protected $definitions = array();

    protected $requireAliases = array();

    protected $useAliases = array();

    public function getNs() {
        return $this->ns;
    }

    public function setNs(string $ns) {
        $this->ns = $ns;
    }

    public function addDefintion(string $namespace, Symbol $name, Table $meta) {
        if (!array_key_exists($namespace, $this->definitions)) {
            $this->definitions[$namespace] = [];
        }

        $this->definitions[$namespace][$name->getName()] = $meta;
    }

    public function hasDefinition(string $namespace, Symbol $name) {
        return (
            isset($this->definitions[$namespace])
            && isset($this->definitions[$namespace][$name->getName()])
        );
    }

    public function getDefinition(string $namespace, Symbol $name) {
        if ($this->hasDefinition($namespace, $name)) {
            return $this->definitions[$namespace][$name->getName()];
        } else {
            return null;
        }
    }

    public function addRequireAlias(Symbol $name, Symbol $fullName) {
        $this->requireAliases[$name->getName()] = $fullName;
    }

    public function hasRequireAlias(Symbol $name) {
        return isset($this->requireAliases[$name->getName()]);
    }
    
    public function addUseAlias(Symbol $alias, Symbol $fullName) {
        $this->useAliases[$alias->getName()] = $fullName;
    }

    public function hasUseAlias(Symbol $alias) {
        return isset($this->useAliases[$alias->getName()]);
    }

    public function resolve(Symbol $name, NodeEnvironment $env) {
        $strName = $name->getName();

        if (substr($strName, 0, 1) == '\\') {
            return new PhpClassNameNode(
                $env,
                $name
            );
        } else if ($this->hasUseAlias($name)) {
            return new PhpClassNameNode(
                $env,
                $this->useAliases[$strName]
            );
        } else {
            $pos = strpos($strName, '/');
            
            if ($pos !== false) {
                // If alias, try to resolve alias
                $alias = substr($strName, 0, $pos);

                if (isset($this->requireAlias[$alias])) {
                    $namespace = $this->requireAlias[$alias];
                    $finalName = new Symbol(substr($strName, $pos+1));

                    if ($this->hasDefinition($namespace, $finalName)) {
                        return new GlobalVarNode($env, $namespace, $name, $this->getDefinition($namespace, $finalName));
                    } else {
                        return null; // Can not be resolved
                    }
                } else {
                    return null; // Can not be resolve;
                }
            } else {
                // no alias, try to resolve in current namespace
                if ($this->hasDefinition($this->getNs(), $name)) {
                    return new GlobalVarNode($env, $this->getNs(), $name, $this->getDefinition($this->getNs(), $name));
                } else {
                    // try to resolve in phel.core namespace
                    $ns = new Symbol('phel\core');
                    if ($this->hasDefinition($ns, $name)) {
                        return new GlobalVarNode($env, $ns, $name, $this->getDefinition($ns, $name));
                    } else {
                        return null; // can not be resolved
                    }
                }
            }
        }        
    }
}