<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\TypedCollectionMethodSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function count;

/**
 * Specialisations gated by {@see TypedCollectionMethodSpecialization}:
 * `(nth v i)` / `(count v)` / `(second v)` on a tagged
 * `PersistentVectorInterface`, and `(first s)` / `(rest s)` on a tagged
 * seq. Each collapses a runtime cond chain over the collection shapes to
 * one direct method call.
 */
final readonly class TypedCollectionCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        if ($this->tryEmitTypedVectorSecond($node)) {
            return true;
        }

        if ($this->tryEmitTypedVectorAccessor($node)) {
            return true;
        }

        return $this->tryEmitTypedSeqAccessor($node);
    }

    /**
     * Specialise `(second v)` on a tagged `PersistentVectorInterface`
     * target. The runtime `phel.core/second` is `(first (next v))`,
     * which returns nil — never throws — when the vector has fewer than
     * two elements. A bare `$v->get(1)` would throw out of range, so the
     * lowering keeps the nil contract behind a length guard:
     * `($v->count() > 1 ? $v->get(1) : null)`. The target is a
     * `LocalVarNode` (a bare variable), so emitting it twice is safe.
     */
    private function tryEmitTypedVectorSecond(CallNode $node): bool
    {
        if (!TypedCollectionMethodSpecialization::isTypedVectorSecond($node)) {
            return false;
        }

        $target = $node->getArguments()[0];
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($target);
        $this->outputEmitter->emitStr('->count() > 1 ? ', $loc);
        $this->outputEmitter->emitNode($target);
        $this->outputEmitter->emitStr('->get(1) : null)', $loc);
        return true;
    }

    /**
     * Specialise `(nth v i)` / `(count v)` to a direct method call on
     * the tagged `PersistentVectorInterface` target. The runtime
     * `phel.core/nth` body walks a `cond` over set / seq / vector /
     * map / php-array; for a typed vector every branch collapses to
     * a single method call.
     */
    private function tryEmitTypedVectorAccessor(CallNode $node): bool
    {
        $spec = TypedCollectionMethodSpecialization::typedVectorMethodCall($node);
        if ($spec === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $spec['method'] . '(', $loc);

        $argCount = count($spec['args']);
        foreach ($spec['args'] as $i => $argIndex) {
            $this->outputEmitter->emitNode($args[$argIndex]);
            if ($i < $argCount - 1) {
                $this->outputEmitter->emitStr(', ', $loc);
            }
        }

        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }

    /**
     * Specialise `(first s)` / `(rest s)` to a direct method call on
     * the tagged seq target. The runtime `phel.core/first` and
     * `phel.core/rest` bodies walk cond chains over nil / string /
     * php-array / set / map / seq; for a known seq tag every branch
     * collapses to `$s->first()` / `$s->rest()`.
     */
    private function tryEmitTypedSeqAccessor(CallNode $node): bool
    {
        $method = TypedCollectionMethodSpecialization::typedSeqMethodName($node);
        if ($method === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $method . '())', $loc);
        return true;
    }
}
