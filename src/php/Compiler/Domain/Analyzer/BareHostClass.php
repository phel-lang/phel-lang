<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Symbol;

use function ltrim;
use function preg_match;

/**
 * A bare all-caps host name standing where only a class can stand.
 *
 * `SymbolResolver` reads such a name as the global constant, because value
 * position is where the two readings genuinely collide and probing the class
 * table there made the emitted PHP depend on what the compiling process had
 * autoloaded (#3064). A member target, a constructor and a callable say
 * "class" by position, so they re-read the constant node as a class name
 * instead of probing anything:
 *
 *     (WP_CLI/log "x")               => \WP_CLI::log("x")
 *     (php/new PDO $dsn)             => new \PDO($dsn)
 *     (php/callable PDO getAvailableDrivers)
 *
 * all of which hold whether or not the class can be loaded here.
 *
 * The bare-constant rule is lexical. A declared PHP class import remains a
 * class in class positions even when a Phel value shadows its alias.
 *
 * `php/NAME` keeps the constant reading in every position, so a constant that
 * holds a class string is still reachable as a dynamic target.
 *
 * @internal
 */
final class BareHostClass
{
    private const string ALL_CAPS = '/^[A-Z_][A-Z0-9_]*$/';

    /**
     * A class position can recover an import shadowed by a Phel var, or turn
     * the bare all-caps constant fallback into its lexical class spelling.
     */
    public static function reread(
        mixed $form,
        ?AbstractNode $resolved,
        NodeEnvironmentInterface $env,
        ?AnalyzerInterface $analyzer = null,
    ): ?PhpClassNameNode {
        if (!$form instanceof Symbol || $form->getNamespace() !== null || $env->hasLocal($form)) {
            return null;
        }

        if ($resolved instanceof GlobalVarNode && $analyzer instanceof AnalyzerInterface) {
            $alias = $analyzer->getUseAliases($analyzer->getNamespace())[$form->getName()] ?? null;
            if ($alias instanceof Symbol) {
                $import = self::fromUseAlias($alias, $form, $env);
                if ($import instanceof PhpClassNameNode) {
                    return $import;
                }
            }
        }

        if (!self::isConstantFallback($form, $resolved)) {
            return null;
        }

        $fqn = Symbol::create('\\' . $form->getName())->copyLocationFrom($form);

        return new PhpClassNameNode($env, $fqn, $form->getStartLocation());
    }

    public static function fromUseAlias(Symbol $alias, Symbol $form, NodeEnvironmentInterface $env): PhpClassNameNode|PhpVarNode
    {
        $alias = $alias->copyLocationFrom($form);
        $target = ltrim($alias->getName(), '\\');
        if ($alias->getNamespace() === null && preg_match(self::ALL_CAPS, $target) === 1 && !PhpClassLike::exists($target)) {
            return new PhpVarNode($env, $alias->getName(), $form->getStartLocation());
        }

        return new PhpClassNameNode($env, $alias, $form->getStartLocation());
    }

    public static function isConstantFallback(Symbol $form, ?AbstractNode $resolved): bool
    {
        return $form->getNamespace() === null
            && $resolved instanceof PhpVarNode
            && $resolved->getName() === $form->getName()
            && preg_match(self::ALL_CAPS, $form->getName()) === 1;
    }
}
