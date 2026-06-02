<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;

/**
 * A single family of call-site specialisations. Each implementation owns the
 * emission of one cohesive group of `phel.core` fns that collapse to a native
 * PHP form when the analyser has proven enough about the call.
 *
 * `tryEmit` returns `true` when it consumed the node (and emitted PHP for it)
 * and `false` otherwise, so {@see \Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\CallEmitter}
 * can chain the families and fall through to the generic dispatch path.
 *
 * The eligibility predicates across families are disjoint by construction
 * (distinct fn names, arities, and analyser tags), so the chain order between
 * families does not affect the emitted output.
 */
interface SpecializedCallEmitterInterface
{
    public function tryEmit(CallNode $node): bool;
}
