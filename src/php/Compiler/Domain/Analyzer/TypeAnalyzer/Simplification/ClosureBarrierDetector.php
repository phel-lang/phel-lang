<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\Simplification;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\MethodCallNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayGetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayPushNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArraySetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpArrayUnsetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNamedArgNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNewNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpRefNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\QuoteNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeChildren;

use function array_any;
use function array_slice;

/**
 * Whether code that ran inside a closure would behave differently spliced
 * into the enclosing PHP scope, which {@see UpdateLiteralFnLowering} does to
 * a literal `fn` body.
 *
 * A closure captures the enclosing locals by value, so its writes to them
 * were to a copy; spliced, they land on the caller's variables. A write is:
 * `php/=` or `php/=&` on an enclosing local or an offset into one; an array
 * write (`php/aset` and friends) whose target mentions one anywhere;
 * `php/ref`; and an enclosing local, or an offset into one (`$arr[0]`),
 * handed to a PHP function or method, since which of those take a reference
 * (`sort`, `preg_match`) is not known here. A `php/yield` would turn the
 * enclosing function into a generator.
 *
 * Nested closures are not walked: they capture by value either way. A node
 * type {@see NodeChildren} does not know is answered `true`, so a new node
 * costs the lowering rather than its semantics.
 *
 * @internal
 */
final readonly class ClosureBarrierDetector
{
    public function needsClosure(AbstractNode $node, NodeEnvironmentInterface $env): bool
    {
        $enclosing = [];
        foreach ($env->getLocals() as $local) {
            $enclosing[($env->getShadowed($local) ?? $local)->getName()] = true;
        }

        return $this->walk($node, $enclosing);
    }

    /**
     * @param array<string, true> $enclosing emitted names of the enclosing locals
     */
    private function walk(AbstractNode $node, array $enclosing): bool
    {
        if ($node instanceof FnNode || $node instanceof MultiFnNode || $node instanceof ReifyNode) {
            return false;
        }

        if ($node instanceof PhpRefNode) {
            return true;
        }

        if ($this->writesThroughArgument($node, $enclosing)) {
            return true;
        }

        if ($node instanceof QuoteNode || $node instanceof PhpClassNameNode) {
            return false;
        }

        $children = NodeChildren::of($node);
        if ($children === null) {
            return true;
        }

        return array_any($children, fn(AbstractNode $child): bool => $this->walk($child, $enclosing));
    }

    /**
     * @param array<string, true> $enclosing
     */
    private function writesThroughArgument(AbstractNode $node, array $enclosing): bool
    {
        if ($node instanceof PhpArraySetNode || $node instanceof PhpArrayPushNode || $node instanceof PhpArrayUnsetNode) {
            return $this->mentionsEnclosingLocal($node->getArrayExpr(), $enclosing);
        }

        if ($node instanceof CallNode) {
            $fn = $node->getFn();
            if (!$fn instanceof PhpVarNode) {
                return false;
            }

            // `php/=` writes its first operand; `php/=&` also ties it to the
            // second, so a later write through either reaches the other.
            if ($fn->getName() === '=') {
                return $this->anyEnclosingLocal(array_slice($node->getArguments(), 0, 1), $enclosing);
            }

            if ($fn->getName() === '=&') {
                return $this->anyEnclosingLocal($node->getArguments(), $enclosing);
            }

            if ($fn->isInfix()) {
                return false;
            }

            return $fn->getName() === 'yield' || $this->anyEnclosingLocal($node->getArguments(), $enclosing);
        }

        if ($node instanceof MethodCallNode || $node instanceof PhpNewNode) {
            return $this->anyEnclosingLocal($node->getArgs(), $enclosing);
        }

        return false;
    }

    /**
     * @param array<int, AbstractNode> $args
     * @param array<string, true>      $enclosing
     */
    private function anyEnclosingLocal(array $args, array $enclosing): bool
    {
        return array_any(
            $args,
            fn(AbstractNode $arg): bool => $this->isEnclosingLocal(
                $this->lvalueRoot($arg instanceof PhpNamedArgNode ? $arg->getValueExpr() : $arg),
                $enclosing,
            ),
        );
    }

    /**
     * The variable an argument would be written through if taken by
     * reference: `$arr[0][1]` writes `$arr`. Anything else is a temporary.
     */
    private function lvalueRoot(AbstractNode $node): AbstractNode
    {
        while ($node instanceof PhpArrayGetNode) {
            $node = $node->getArrayExpr();
        }

        return $node;
    }

    /**
     * The target of an array write, whatever wraps it: any enclosing local
     * in it keeps the closure.
     *
     * @param array<string, true> $enclosing
     */
    private function mentionsEnclosingLocal(AbstractNode $node, array $enclosing): bool
    {
        if ($this->isEnclosingLocal($node, $enclosing)) {
            return true;
        }

        $children = NodeChildren::of($node);
        if ($children === null) {
            return true;
        }

        return array_any($children, fn(AbstractNode $child): bool => $this->mentionsEnclosingLocal($child, $enclosing));
    }

    /**
     * @param array<string, true> $enclosing
     */
    private function isEnclosingLocal(AbstractNode $node, array $enclosing): bool
    {
        return $node instanceof LocalVarNode && isset($enclosing[$node->getName()->getName()]);
    }
}
