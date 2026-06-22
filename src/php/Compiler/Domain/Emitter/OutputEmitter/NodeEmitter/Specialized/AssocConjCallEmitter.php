<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\AssocConjSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function array_slice;

/**
 * Specialisations gated by {@see AssocConjSpecialization}: a transient-backed
 * chain of `(assoc m k v)` / `(conj v x)` calls, the variadic
 * `(dissoc m k1 k2 …)` lowered to a `->remove()` chain, and the single-step
 * `(assoc ...)` / `(conj ...)` / `(push ...)` on a typed persistent target.
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

        if ($this->tryEmitTypedDissocKeys($node)) {
            return true;
        }

        return $this->tryEmitTypedAssocConjDissoc($node);
    }

    /**
     * Specialise `(dissoc m k1 k2 …)` on a `PersistentMapInterface`-tagged
     * target to a chain of `->remove($k)` calls — one per key, in the same
     * left-to-right order the runtime `dissoc` loop applies them. Each
     * `remove` returns a new persistent map, so chaining matches the
     * runtime's per-key folding semantics. Owns every typed `dissoc`
     * (including the single-key arity, which emits the same `->remove($k)`
     * the generic single-step path would).
     */
    private function tryEmitTypedDissocKeys(CallNode $node): bool
    {
        $keys = AssocConjSpecialization::typedDissocKeys($node);
        if ($keys === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);

        foreach ($keys as $key) {
            $this->outputEmitter->emitStr('->remove(', $loc);
            $this->outputEmitter->emitNode($key);
            $this->outputEmitter->emitStr(')', $loc);
        }

        $this->outputEmitter->emitStr(')', $loc);
        return true;
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
            $this->outputEmitter->emitArgList($group, $loc);
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
        $this->outputEmitter->emitArgList(array_slice($args, 1), $loc);
        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }
}
