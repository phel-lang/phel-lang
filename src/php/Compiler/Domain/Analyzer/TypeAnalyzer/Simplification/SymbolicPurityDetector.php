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
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\SetNode;
use Phel\Compiler\Domain\Analyzer\Ast\VectorNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Shared\CompilerConstants;

use function array_all;

/**
 * Symbolic purity oracle for the call inliner.
 *
 * Unlike {@see PureExpressionDetector} — which proves purity by fully
 * evaluating a node at compile time and therefore reports any expression
 * over a free variable as impure — this detector decides purity
 * *structurally*: an expression is pure when it only references
 * literals / locals / globals and calls a closed allowlist of
 * side-effect-free `phel.core` fns (or their `php/` infix equivalents)
 * on pure arguments.
 *
 * "Pure" here means: evaluating the node has no observable effect (no
 * I/O, no mutation, no global state change). A pure expression may still
 * raise (e.g. `(/ x 0)`) — the inliner splices the body exactly once, so
 * any such exception keeps its original trigger point.
 *
 * The fn allowlist mirrors the scalar operations {@see ConstantFolder}
 * already evaluates at compile time: that set is, by construction, a
 * vetted list of effect-free core fns.
 */
final readonly class SymbolicPurityDetector
{
    /**
     * Side-effect-free `phel.core` fns. Drawn from the scalar ops
     * {@see \Phel\Compiler\Domain\Analyzer\TypeAnalyzer\ConstantFolder::compute()}
     * and its boolean predicates already trust as pure enough to evaluate
     * at compile time. This set is intentionally *wider* in applicability:
     * the folder only fires on literal arguments, whereas the inliner
     * accepts these ops over free variables too, so it must be updated
     * separately when a new pure-but-not-foldable core fn appears.
     *
     * @var array<string, true>
     */
    private const array PURE_CORE_FNS = [
        '+' => true,
        '-' => true,
        '*' => true,
        '/' => true,
        'inc' => true,
        'dec' => true,
        '=' => true,
        'not=' => true,
        '<' => true,
        '<=' => true,
        '>' => true,
        '>=' => true,
        'min' => true,
        'max' => true,
        'mod' => true,
        'quot' => true,
        'rem' => true,
        'abs' => true,
        'not' => true,
        'nil?' => true,
        'true?' => true,
        'false?' => true,
        'boolean' => true,
    ];

    /**
     * Side-effect-free `php/` infix operators. Excludes `=` / `=&`
     * (assignment), `.` (concat / property access ambiguity) and
     * `instanceof`, none of which the inliner needs.
     *
     * @var array<string, true>
     */
    private const array PURE_PHP_OPS = [
        '+' => true,
        '-' => true,
        '*' => true,
        '/' => true,
        '%' => true,
        '**' => true,
        '==' => true,
        '===' => true,
        '!=' => true,
        '!==' => true,
        '<' => true,
        '>' => true,
        '<=' => true,
        '>=' => true,
        '<=>' => true,
        '|' => true,
        '&' => true,
        '^' => true,
        '<<' => true,
        '>>' => true,
        '~' => true,
    ];

    public function isPure(AbstractNode $node): bool
    {
        if ($node instanceof LiteralNode
            || $node instanceof LocalVarNode
            || $node instanceof GlobalVarNode
            || $node instanceof PhpVarNode
        ) {
            return true;
        }

        if ($node instanceof IfNode) {
            return $this->isPure($node->getTestExpr())
                && $this->isPure($node->getThenExpr())
                && $this->isPure($node->getElseExpr());
        }

        if ($node instanceof CallNode) {
            return $this->isPureCall($node);
        }

        // Building a persistent collection is side-effect-free, so a
        // literal is pure when every element is. Reader-attached meta
        // (`^{…}`) is treated as impure here so the classification stays
        // in lockstep with what the call inliner can rebase.
        if ($node instanceof VectorNode) {
            return !$node->getMeta() instanceof MapNode && $this->allPure($node->getArgs());
        }

        if ($node instanceof SetNode) {
            return !$node->getMeta() instanceof MapNode && $this->allPure($node->getValues());
        }

        if ($node instanceof MapNode) {
            return !$node->getLiteralMeta() instanceof MapNode && $this->allPure($node->getKeyValues());
        }

        // A non-loop `let` is pure when every binding init and its body are
        // pure: introducing locals and reading them back has no observable
        // effect. A `loop` is excluded — `recur` is control flow the
        // inliner never rebases. This keeps the oracle in lockstep with
        // what {@see CallInliner} can splice, so a let-bodied pure `defn`
        // both passes the purity gate and rebases.
        if ($node instanceof LetNode) {
            return !$node->isLoop()
                && $this->allPureBindings($node)
                && $this->isPure($node->getBodyExpr());
        }

        // `let`/`if`/`fn` bodies are wrapped in a `DoNode`; it is pure when
        // every leading statement and the return expression are pure.
        if ($node instanceof DoNode) {
            return $this->allPure($node->getStmts()) && $this->isPure($node->getRet());
        }

        return false;
    }

    /**
     * @param PersistentMapInterface<mixed, mixed> $meta
     */
    public function isPureAnnotated(PersistentMapInterface $meta): bool
    {
        return (bool) $meta[Keyword::create('pure')];
    }

    private function allPureBindings(LetNode $node): bool
    {
        return array_all(
            $node->getBindings(),
            fn(BindingNode $binding): bool => $this->isPure($binding->getInitExpr()),
        );
    }

    /**
     * @param array<int, AbstractNode> $nodes
     */
    private function allPure(array $nodes): bool
    {
        return array_all($nodes, fn(AbstractNode $node): bool => $this->isPure($node));
    }

    private function isPureCall(CallNode $node): bool
    {
        return $this->isPureOperator($node->getFn())
            && $this->allPure($node->getArguments());
    }

    private function isPureOperator(AbstractNode $fn): bool
    {
        if ($fn instanceof PhpVarNode) {
            return isset(self::PURE_PHP_OPS[$fn->getName()]);
        }

        if ($fn instanceof GlobalVarNode) {
            if ($fn->getNamespace() === CompilerConstants::PHEL_CORE_NAMESPACE
                && isset(self::PURE_CORE_FNS[$fn->getName()->getName()])
            ) {
                return true;
            }

            // Opt-in trust: a `defn` tagged `^:pure` asserts that its calls
            // are side-effect-free, so the inliner may treat them like the
            // built-in pure ops. Mis-annotation is the author's responsibility.
            return $this->isPureAnnotated($fn->getMeta());
        }

        return false;
    }
}
