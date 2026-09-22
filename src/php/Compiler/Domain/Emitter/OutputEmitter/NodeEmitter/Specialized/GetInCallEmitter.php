<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\GetInSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\GetIn;

/**
 * Specialisation gated by {@see GetInSpecialization}: `(get-in coll [k1 k2 …])`
 * with a literal path. On a tagged persistent collection it is unrolled into
 * a null-coalescing subscript chain; on any other target it becomes one
 * {@see GetIn::path()} call with the keys as a PHP array (#3320).
 *
 * @internal
 */
final readonly class GetInCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        $keys = GetInSpecialization::subscriptChainKeys($node);
        if ($keys !== null) {
            $this->emitSubscriptChain($node, $keys);
            return true;
        }

        $keys = GetInSpecialization::literalPathKeys($node);
        if ($keys === null) {
            return false;
        }

        $this->emitPathLookup($node, $keys);
        return true;
    }

    /**
     * Every argument is written once and in source order, the target, then
     * the keys, then the default, so they evaluate as the call's arguments
     * did. Nothing is written twice, so nested lookups stay linear in size,
     * unlike a ternary guard that repeats its arguments in both arms.
     *
     * @param list<AbstractNode> $keys
     */
    private function emitPathLookup(CallNode $node, array $keys): void
    {
        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitStr('\\' . GetIn::class . '::path(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr(', ', $loc);

        // A path of literals is hoisted whole, as the vector it replaces was,
        // so the key array is built once per fn rather than once per call.
        $cached = $this->outputEmitter->emitConstantSlotPrefix($args[1], $loc);
        $this->outputEmitter->emitStr('[', $loc);
        $this->outputEmitter->emitArgList($keys, $loc);
        $this->outputEmitter->emitStr(']', $loc);
        if ($cached) {
            $this->outputEmitter->emitConstantSlotSuffix($loc);
        }

        if (isset($args[2])) {
            $this->outputEmitter->emitStr(', ', $loc);
            $this->outputEmitter->emitNode($args[2]);
        }

        $this->outputEmitter->emitStr(')', $loc);
    }

    /**
     * @param list<AbstractNode> $keys
     */
    private function emitSubscriptChain(CallNode $node, array $keys): void
    {
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
    }
}
