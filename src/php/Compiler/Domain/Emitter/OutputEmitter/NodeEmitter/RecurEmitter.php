<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\RecurNode;
use Phel\Compiler\Domain\Analyzer\Ast\Reference\LocalVarReferences;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;

use function array_search;
use function assert;

final class RecurEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof RecurNode);

        if ($this->canSkipTempVars($node)) {
            $this->emitDirect($node);
        } else {
            $this->emitViaTempVars($node);
        }

        $this->outputEmitter->emitLine('continue;', $node->getStartSourceLocation());
    }

    /**
     * Direct path: each recur expression is assigned straight to its
     * matching param. Safe only when no expression reads a param that an
     * earlier expression has already overwritten.
     */
    private function emitDirect(RecurNode $node): void
    {
        $shadows = $node->getFrame()->getShadows();
        foreach ($node->getExpressions() as $i => $expr) {
            $targetSym = $shadows[$i];

            $this->outputEmitter->emitPhpVariable($targetSym, $targetSym->getStartLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($expr);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }
    }

    /**
     * Aliasing-safe path: evaluate every expression into a fresh temp,
     * then assign each temp to its param. Handles patterns like
     * `(recur b a)` (swap) where naive emission would clobber.
     */
    private function emitViaTempVars(RecurNode $node): void
    {
        $tempSyms = [];
        foreach ($node->getExpressions() as $expr) {
            $tempSym = Symbol::gen();
            $tempSyms[] = $tempSym;

            $this->outputEmitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($expr);
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        $shadows = $node->getFrame()->getShadows();

        foreach ($tempSyms as $i => $tempSym) {
            $targetSym = $shadows[$i];

            $this->outputEmitter->emitPhpVariable($targetSym, $targetSym->getStartLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitPhpVariable($tempSym, $node->getStartSourceLocation());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }
    }

    /**
     * Temp vars are only needed when an expression at index `i` reads a
     * recur param at index `j` with `j < i`. That param has already been
     * overwritten by the direct path's earlier assignment, so the read
     * would return the new value instead of the original. Self-reference
     * (`j == i`) is safe because PHP evaluates the RHS before the
     * assignment; forward reference (`j > i`) is also safe because the
     * later param hasn't been touched yet.
     */
    private function canSkipTempVars(RecurNode $node): bool
    {
        // For each param slot, the recur emit assigns to the frame's own
        // target (shadow) PHP variable. Match against the same name that any
        // `LocalVarNode` in the recur expressions resolves to, so the
        // detector compares apples to apples.
        $paramNames = [];
        foreach ($node->getFrame()->getShadows() as $shadow) {
            $paramNames[] = $shadow->getName();
        }

        foreach ($node->getExpressions() as $i => $expr) {
            $refs = LocalVarReferences::collect($expr);
            if ($refs === null) {
                // Scanner saw a node it does not enumerate; assume the
                // expression could reference any param and fall back to
                // the temp-var path.
                return false;
            }

            foreach ($refs as $referencedName) {
                $refIdx = array_search($referencedName, $paramNames, true);
                if ($refIdx !== false && $refIdx < $i) {
                    return false;
                }
            }
        }

        return true;
    }
}
