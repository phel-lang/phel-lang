<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\GetInSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

/**
 * Specialisation gated by {@see GetInSpecialization}: `(get-in coll [k1 k2 …])`
 * with a literal path on a tagged persistent collection, unrolled into a
 * null-coalescing subscript chain.
 */
final readonly class GetInCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        $keys = GetInSpecialization::literalPathKeys($node);
        if ($keys === null) {
            return false;
        }

        $target = $node->getArguments()[0];
        $loc = $node->getStartSourceLocation();

        // One `(` per level so each `?? null` binds to the access at its own
        // level. The shape mirrors `php/aget`'s `($coll[($k)] ?? null)`, which
        // returns nil on a missing key (PHP's `??` checks `offsetExists`
        // first, so the chain never throws) and recurses on the result.
        foreach ($keys as $_) {
            $this->outputEmitter->emitStr('(', $loc);
        }

        $this->outputEmitter->emitNode($target);

        foreach ($keys as $key) {
            $this->outputEmitter->emitStr('[(', $loc);
            $this->outputEmitter->emitNode($key);
            $this->outputEmitter->emitStr(')] ?? null)', $loc);
        }

        return true;
    }
}
