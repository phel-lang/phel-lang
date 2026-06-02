<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\AssocConjSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function array_slice;
use function count;

/**
 * Specialisations gated by {@see AssocConjSpecialization}: a transient-backed
 * chain of `(assoc m k v)` / `(conj v x)` calls, and the single-step
 * `(assoc ...)` / `(conj ...)` / `(dissoc ...)` on a typed persistent target.
 */
final readonly class AssocConjCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        if ($this->tryEmitAssocConjChain($node)) {
            return true;
        }

        return $this->tryEmitTypedAssocConjDissoc($node);
    }

    /**
     * Specialise a chain of `(assoc m k v)` calls (or `(conj v x)`
     * calls) on a typed persistent target — after thread-macro
     * expansion these are nested `CallNode`s of the same op rooted at
     * a `LocalVarNode`. The runtime path goes through one persistent
     * `put` / `append` per chain step, each allocating a new persistent
     * map / vector. The transient path opens one transient at the leaf
     * target, mutates it once per chain step, and snapshots back to a
     * persistent at the end — N-1 persistent intermediates collapse to
     * one.
     */
    private function tryEmitAssocConjChain(CallNode $node): bool
    {
        $chain = AssocConjSpecialization::assocConjChain($node);
        if ($chain === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('((', $loc);
        $this->outputEmitter->emitNode($chain['target']);
        $this->outputEmitter->emitStr(')->asTransient()', $loc);

        foreach ($chain['groups'] as $group) {
            $this->outputEmitter->emitStr('->' . $chain['method'] . '(', $loc);
            foreach ($group as $i => $arg) {
                if ($i > 0) {
                    $this->outputEmitter->emitStr(', ', $loc);
                }

                $this->outputEmitter->emitNode($arg);
            }

            $this->outputEmitter->emitStr(')', $loc);
        }

        $this->outputEmitter->emitStr('->persistent())', $loc);
        return true;
    }

    /**
     * Specialise `(assoc m k v)` / `(assoc v i x)` / `(conj v x)` /
     * `(dissoc m k)` to a direct persistent-collection method when the
     * target tag is known. Skips variadic forms.
     */
    private function tryEmitTypedAssocConjDissoc(CallNode $node): bool
    {
        $method = AssocConjSpecialization::typedAssocConjDissocMethod($node);
        if ($method === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $method . '(', $loc);

        $rest = array_slice($args, 1);
        $count = count($rest);
        foreach ($rest as $i => $arg) {
            $this->outputEmitter->emitNode($arg);
            if ($i < $count - 1) {
                $this->outputEmitter->emitStr(', ', $loc);
            }
        }

        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }
}
