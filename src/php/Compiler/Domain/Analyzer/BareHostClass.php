<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Symbol;

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
 * Purely lexical, and the single statement of the class-position rule, in the
 * same spirit as {@see QualifiedMemberSyntax}: the three call sites cannot
 * disagree about what counts as a class.
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
     * The class-name node for a form that was a bare all-caps symbol and
     * resolved to the constant fallback, or `null` when the form was anything
     * else.
     *
     * The `(Symbol, PhpVarNode of the same name)` pair is what identifies the
     * fallback: a local, a Phel definition and `php/NAME` all resolve to other
     * node types or other names.
     */
    public static function reread(
        mixed $form,
        ?AbstractNode $resolved,
        NodeEnvironmentInterface $env,
    ): ?PhpClassNameNode {
        if (!$form instanceof Symbol || $form->getNamespace() !== null) {
            return null;
        }

        if (!$resolved instanceof PhpVarNode || $resolved->getName() !== $form->getName()) {
            return null;
        }

        if (preg_match(self::ALL_CAPS, $form->getName()) !== 1) {
            return null;
        }

        $fqn = Symbol::create('\\' . $form->getName())->copyLocationFrom($form);

        return new PhpClassNameNode($env, $fqn, $form->getStartLocation());
    }
}
