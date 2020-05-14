<?php

namespace Phel;

use Phel\Ast\GlobalVarNode;
use Phel\Ast\Node;
use Phel\Ast\PhpClassNameNode;
use Phel\Lang\Symbol;
use Phel\Lang\Table;

class GlobalEnvironment {

    /**
     * @var string
     */
    protected $ns = 'user';

    /**
     * @var array
     */
    protected $definitions = array();

    /**
     * @var array
     */
    protected $requireAliases = array();

    /**
     * @var array
     */
    protected $useAliases = array();

    public function getNs(): string {
        return $this->ns;
    }

    public function setNs(string $ns): void {
        $this->ns = $ns;
    }

    public function addDefintion(string $namespace, Symbol $name, Table $meta): void {
        if (!array_key_exists($namespace, $this->definitions)) {
            $this->definitions[$namespace] = [];
        }

        $this->definitions[$namespace][$name->getName()] = $meta;
    }

    public function hasDefinition(string $namespace, Symbol $name): bool {
        return (
            isset($this->definitions[$namespace])
            && isset($this->definitions[$namespace][$name->getName()])
        );
    }

    public function getDefinition(string $namespace, Symbol $name): ?Table {
        if ($this->hasDefinition($namespace, $name)) {
            return $this->definitions[$namespace][$name->getName()];
        } else {
            return null;
        }
    }

    public function addRequireAlias(Symbol $name, Symbol $fullName): void {
        $this->requireAliases[$name->getName()] = $fullName;
    }

    public function hasRequireAlias(Symbol $name): bool {
        return isset($this->requireAliases[$name->getName()]);
    }
    
    public function addUseAlias(Symbol $alias, Symbol $fullName): void {
        $this->useAliases[$alias->getName()] = $fullName;
    }

    public function hasUseAlias(Symbol $alias): bool {
        return isset($this->useAliases[$alias->getName()]);
    }

    public function resolve(Symbol $name, NodeEnvironment $env): ?Node {
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
            
            if ($pos !== FALSE && $pos > 0) {
                // If alias, try to resolve alias
                $alias = substr($strName, 0, $pos);

                if (isset($this->requireAlias[$alias])) {
                    $namespace = $this->requireAlias[$alias];
                    $finalName = new Symbol(substr($strName, $pos+1));

                    $def = $this->getDefinition($namespace, $finalName);
                    if ($def) {
                        return new GlobalVarNode($env, $namespace, $name, $def);
                    } else {
                        return null; // Can not be resolved
                    }
                } else {
                    return null; // Can not be resolve;
                }
            } else {
                // no alias, try to resolve in current namespace
                $def = $this->getDefinition($this->getNs(), $name); 
                if ($def) {
                    return new GlobalVarNode($env, $this->getNs(), $name, $def);
                } else {
                    // try to resolve in phel.core namespace
                    $ns = 'phel\core';
                    $def = $this->getDefinition($ns, $name);
                    if ($def) {
                        return new GlobalVarNode($env, $ns, $name, $def);
                    } else {
                        return null; // can not be resolved
                    }
                }
            }
        }        
    }
}