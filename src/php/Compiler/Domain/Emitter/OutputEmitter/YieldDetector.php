<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\FnNode;
use Phel\Compiler\Domain\Analyzer\Ast\MultiFnNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\ReifyNode;

use function array_any;

/**
 * Reports whether a node subtree contains a `(php/yield …)` in the current
 * function frame. PHP promotes any function containing `yield` to a generator,
 * so an emitter can only drop the IIFE around a construct (e.g. a return-context
 * `foreach`) once it knows the wrapper is not acting as that generator boundary:
 * eliding it would move the `yield` up to the enclosing fn, making *it* the
 * generator and deferring its pre-loop side-effects to first iteration.
 *
 * Traversal stops at closure boundaries (`FnNode`, `MultiFnNode`, `ReifyNode`):
 * a `yield` inside a nested closure makes that closure the generator, not the
 * frame we are in, so it does not force the wrapper here.
 *
 * It **fails closed**: a node whose shape {@see NodeChildren} does not recognise
 * is assumed to possibly yield, so the wrapper is kept. Under-recognising only
 * costs an unnecessary IIFE; it can never silently misplace a generator boundary.
 */
final class YieldDetector
{
    public function containsYield(AbstractNode $node): bool
    {
        if ($node instanceof CallNode) {
            $fnNode = $node->getFn();
            if ($fnNode instanceof PhpVarNode && $fnNode->getName() === 'yield') {
                return true;
            }
        }

        if ($node instanceof FnNode || $node instanceof MultiFnNode || $node instanceof ReifyNode) {
            return false;
        }

        $children = NodeChildren::of($node);
        if ($children === null) {
            return true;
        }

        return array_any($children, fn(AbstractNode $child): bool => $this->containsYield($child));
    }
}
