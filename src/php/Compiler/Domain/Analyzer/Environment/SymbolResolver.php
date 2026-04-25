<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Registry;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;
use RuntimeException;

use function class_exists;
use function interface_exists;
use function trait_exists;

final readonly class SymbolResolver
{
    public function __construct(
        private GlobalEnvironment $globalEnv,
        private MagicConstantResolver $magicConstantResolver,
        private ?BackslashSeparatorDeprecator $backslashDeprecator = null,
    ) {}

    public function resolve(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode
    {
        $this->backslashDeprecator?->maybeWarn($name);
        $strName = $name->getName();

        if ($strName === '__DIR__') {
            return new LiteralNode(
                $env,
                $this->magicConstantResolver->resolveDir($name->getStartLocation()),
            );
        }

        if ($strName === '__FILE__') {
            return new LiteralNode(
                $env,
                $this->magicConstantResolver->resolveFile($name->getStartLocation()),
            );
        }

        if ($strName[0] === '\\') {
            return new PhpClassNameNode($env, $name, $name->getStartLocation());
        }

        if ($name->getNamespace() === null && $this->looksLikeDotSeparatedClassFqn($strName)) {
            $fqn = Symbol::create('\\' . str_replace('.', '\\', $strName));
            $fqn->copyLocationFrom($name);

            return new PhpClassNameNode($env, $fqn, $name->getStartLocation());
        }

        $useAliasNode = $this->resolveFromUseAlias($name, $env);
        if ($useAliasNode instanceof AbstractNode) {
            return $useAliasNode;
        }

        $resolved = ($name->getNamespace() !== null)
            ? $this->resolveWithAlias($name, $env)
            : $this->resolveWithoutAlias($name, $env);

        if ($resolved instanceof AbstractNode) {
            return $resolved;
        }

        // Fallback: a bare class identifier with no other resolution is
        // treated as a PHP root-namespace class FQN. Uppercase names keep
        // Clojure-style behavior for unloaded classes, while lowercase PHP
        // built-ins like `stdClass` resolve when PHP already knows them.
        // Kicks in *after* normal resolution so `(def Foo ...)` still wins.
        if ($name->getNamespace() === null && $this->looksLikeBareClassName($strName)) {
            $fqn = Symbol::create('\\' . $strName);
            $fqn->copyLocationFrom($name);

            return new PhpClassNameNode($env, $fqn, $name->getStartLocation());
        }

        return null;
    }

    private function resolveFromUseAlias(Symbol $name, NodeEnvironmentInterface $env): ?PhpClassNameNode
    {
        $currentNs = $this->globalEnv->getNs();
        $useAliases = $this->globalEnv->getUseAliases($currentNs);
        $strName = $name->getName();

        if (!isset($useAliases[$strName])) {
            return null;
        }

        $alias = $useAliases[$strName];
        $alias->copyLocationFrom($name);

        return new PhpClassNameNode($env, $alias, $name->getStartLocation());
    }

    private function resolveWithAlias(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode
    {
        $alias = $name->getNamespace();
        if ($alias === null) {
            throw new RuntimeException('resolveWithAlias called with a Symbol without namespace');
        }

        $finalName = Symbol::create($name->getName());

        $normalizedAlias = str_replace('.', '\\', $alias);
        $normalizedAlias = $this->remapClojureAlias($normalizedAlias);

        $ns = $this->globalEnv->resolveAlias($normalizedAlias) ?? $normalizedAlias;

        return $this->resolveInterfaceOrDefinition($finalName, $env, $ns);
    }

    /**
     * Accept `Foo.Bar.Baz` as an alias for the PHP class FQN `\Foo\Bar\Baz`,
     * matching Clojure's class-reference syntax in `.cljc` code. Only applies
     * to names that look like class FQNs (contain a dot, start uppercase) so
     * plain Phel symbols are left to the normal resolution path.
     */
    private function looksLikeDotSeparatedClassFqn(string $name): bool
    {
        return preg_match('/^[A-Z]\w*(\.[A-Za-z_]\w*)+$/', $name) === 1;
    }

    /**
     * Accept bare uppercase identifiers as root-namespace PHP class FQN
     * aliases, so `Exception` resolves the same as `\Exception`. Also
     * accepts known PHP class/interface/trait names regardless of leading
     * case, so `stdClass` matches PHP's case-insensitive class lookup.
     */
    private function looksLikeBareClassName(string $name): bool
    {
        if (preg_match('/^[A-Z]\w*$/', $name) === 1) {
            return true;
        }

        return preg_match('/^[A-Za-z_]\w*$/', $name) === 1
            && (
                class_exists($name, false)
                || interface_exists($name, false)
                || trait_exists($name, false)
            );
    }

    private function remapClojureAlias(string $alias): string
    {
        if (!str_starts_with($alias, 'clojure\\')) {
            return $alias;
        }

        $targetNs = 'phel\\' . substr($alias, 8);
        $mungedNs = str_replace('-', '_', $targetNs);

        if (Registry::getInstance()->getDefinitionInNamespace($mungedNs) === []) {
            return $alias;
        }

        return $targetNs;
    }

    private function resolveWithoutAlias(Symbol $name, NodeEnvironmentInterface $env): ?AbstractNode
    {
        $currentNs = $this->globalEnv->getNs();
        $refers = $this->globalEnv->getRefers($currentNs);

        if (isset($refers[$name->getName()])) {
            $referNs = $refers[$name->getName()]->getName();

            return $this->resolveInterfaceOrDefinition($name, $env, $referNs)
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
        $interfaceNode = $this->resolveInterface($name, $env, $ns);
        if ($interfaceNode instanceof PhpClassNameNode) {
            return $interfaceNode;
        }

        $def = $this->globalEnv->getDefinition($ns, $name);
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
        $interfaceNode = $this->resolveInterface($name, $env, $ns);
        if ($interfaceNode instanceof PhpClassNameNode) {
            return $interfaceNode;
        }

        $def = $this->globalEnv->getDefinition($ns, $name);
        if (!$def instanceof PersistentMapInterface) {
            return null;
        }

        if (!$this->isPrivateDefinitionAllowed($def)) {
            return null;
        }

        return new GlobalVarNode($env, $ns, $name, $def, $name->getStartLocation());
    }

    private function resolveInterface(
        Symbol $name,
        NodeEnvironmentInterface $env,
        string $ns,
    ): ?PhpClassNameNode {
        $interfaces = $this->globalEnv->getInterfaces($ns);
        if (!isset($interfaces[$name->getName()])) {
            return null;
        }

        return new PhpClassNameNode(
            $env,
            Symbol::createForNamespace($ns, $name->getName()),
            $name->getStartLocation(),
        );
    }

    private function isPrivateDefinitionAllowed(PersistentMapInterface $meta): bool
    {
        if ($this->globalEnv->isPrivateAccessAllowed()) {
            return true;
        }

        return !$meta[Keyword::create('private')];
    }
}
