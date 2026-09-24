<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\BindingNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\DoNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\IfNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MapNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Lang\Symbol;

use function array_slice;
use function assert;

/**
 * Gives the params of a spliced fn body fresh PHP names
 * ({@see UpdateLiteralFnLowering}).
 *
 * A closure's params live in its own frame. Spliced, they would be variables
 * of the caller's frame under their source names, where `compact` or
 * `get_defined_vars` could find them; a `let` would have left a fresh name
 * there instead. So every reference to a param becomes a reference to its
 * fresh name, and every environment that still sees the param maps it to
 * that name, which is what an IIFE's `use(...)` clause reads (the body's
 * value may be re-homed into one, {@see ExpressionContextRebuilder}).
 *
 * Only the node types {@see SpliceableBody} accepts are rebuilt. A nested
 * `fn` is not: its body and its capture list name the param, and rewriting
 * them is not worth it, so `null` declines the whole lowering, as does any
 * other node.
 *
 * @internal
 */
final readonly class ParamRenamer
{
    /**
     * @param array<string, Symbol> $fresh param name => fresh symbol
     */
    public function __construct(
        private array $fresh,
    ) {}

    public function rename(AbstractNode $node): ?AbstractNode
    {
        $env = $this->env($node->getEnv());
        $loc = $node->getStartSourceLocation();

        if ($node instanceof LocalVarNode) {
            $fresh = $this->fresh[$node->getName()->getName()] ?? null;

            return $fresh instanceof Symbol ? new LocalVarNode($env, $fresh, $loc) : $node;
        }

        // Leaves carry the environment too: re-homing one into expression
        // position may wrap it in an IIFE, whose capture list reads it.
        return match (true) {
            $node instanceof LiteralNode => new LiteralNode($env, $node->getValue(), $loc),
            $node instanceof QuoteNode => new QuoteNode($env, $node->getValue(), $loc),
            $node instanceof GlobalVarNode => new GlobalVarNode($env, $node->getNamespace(), $node->getName(), $node->getMeta(), $loc),
            $node instanceof PhpVarNode => new PhpVarNode($env, $node->getName(), $loc),
            $node instanceof CallNode => $this->call($node, $env),
            // Reader metadata is not renamed; {@see SpliceableBody} already
            // turns such a collection away.
            $node instanceof VectorNode && !$node->getMeta() instanceof MapNode => $this->each($node->getArgs(), static fn(array $args): VectorNode => new VectorNode($env, $args, $loc)),
            $node instanceof MapNode && !$node->getLiteralMeta() instanceof MapNode => $this->each($node->getKeyValues(), static fn(array $kvs): MapNode => new MapNode($env, $kvs, $loc)),
            $node instanceof SetNode && !$node->getMeta() instanceof MapNode => $this->each($node->getValues(), static fn(array $values): SetNode => new SetNode($env, $values, $loc)),
            $node instanceof PhpArrayGetNode => $this->each(
                [$node->getArrayExpr(), ...$node->getAccessExprs()],
                static fn(array $parts): PhpArrayGetNode => new PhpArrayGetNode($env, $parts[0], array_slice($parts, 1), $loc),
            ),
            $node instanceof IfNode => $this->each(
                [$node->getTestExpr(), $node->getThenExpr(), $node->getElseExpr()],
                static fn(array $parts): IfNode => new IfNode($env, $parts[0], $parts[1], $parts[2], $loc),
            ),
            $node instanceof DoNode => $this->each(
                [...$node->getStmts(), $node->getRet()],
                static function (array $parts) use ($env, $loc): DoNode {
                    $ret = array_pop($parts);
                    assert($ret instanceof AbstractNode);

                    return new DoNode($env, $parts, $ret, $loc);
                },
            ),
            $node instanceof LetNode && !$node->isLoop() => $this->let($node, $env),
            default => null,
        };
    }

    /**
     * The environment with every param it still sees mapped to its fresh
     * name. A param already shadowed there was rebound by an inner `let`,
     * whose own shadow must win.
     */
    private function env(NodeEnvironmentInterface $env): NodeEnvironmentInterface
    {
        foreach ($this->fresh as $name => $fresh) {
            $param = Symbol::create($name);
            if ($env->hasLocal($param) && !$env->isShadowed($param)) {
                $env = $env->withShadowedLocal($param, $fresh);
            }
        }

        return $env;
    }

    private function call(CallNode $node, NodeEnvironmentInterface $env): ?CallNode
    {
        return $this->each(
            [$node->getFn(), ...$node->getArguments()],
            static fn(array $parts): CallNode => new CallNode($env, $parts[0], array_slice($parts, 1), $node->getStartSourceLocation()),
        );
    }

    private function let(LetNode $node, NodeEnvironmentInterface $env): ?LetNode
    {
        $bindings = [];
        foreach ($node->getBindings() as $binding) {
            $init = $this->rename($binding->getInitExpr());
            if (!$init instanceof AbstractNode) {
                return null;
            }

            $bindings[] = new BindingNode(
                $this->env($binding->getEnv()),
                $binding->getSymbol(),
                $binding->getShadow(),
                $init,
                $binding->getStartSourceLocation(),
            );
        }

        $body = $this->rename($node->getBodyExpr());
        if (!$body instanceof AbstractNode) {
            return null;
        }

        return new LetNode($env, $bindings, $body, false, $node->getStartSourceLocation(), $node->getCallerBindingCount());
    }

    /**
     * Renames each node and hands the results to `$build`, or `null` as soon
     * as one declines.
     *
     * @template T of AbstractNode
     *
     * @param array<int, AbstractNode>        $nodes
     * @param callable(list<AbstractNode>): T $build
     *
     * @return T|null
     */
    private function each(array $nodes, callable $build): ?AbstractNode
    {
        $renamed = [];
        foreach ($nodes as $node) {
            $result = $this->rename($node);
            if (!$result instanceof AbstractNode) {
                return null;
            }

            $renamed[] = $result;
        }

        return $build($renamed);
    }
}
