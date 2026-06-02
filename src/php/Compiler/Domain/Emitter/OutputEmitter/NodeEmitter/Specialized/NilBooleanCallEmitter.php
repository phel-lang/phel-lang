<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NilAndBooleanCheckSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

/**
 * Specialisations gated by {@see NilAndBooleanCheckSpecialization}:
 * `(nil? x)`, `(some? x)`, `(true? x)`, `(false? x)`, and `(truthy? x)`,
 * each inlined to the native identity check, bypassing the registry lookup
 * and the `id` adapter.
 */
final readonly class NilBooleanCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        if ($this->tryEmitNilCheck($node)) {
            return true;
        }

        if ($this->tryEmitSomeCheck($node)) {
            return true;
        }

        if ($this->tryEmitTrueCheck($node)) {
            return true;
        }

        if ($this->tryEmitFalseCheck($node)) {
            return true;
        }

        return $this->tryEmitTruthyCheck($node);
    }

    /**
     * `(nil? x)` — emit `($x === null)` directly, bypassing the
     * registry lookup and `id` adapter.
     */
    private function tryEmitNilCheck(CallNode $node): bool
    {
        if (!NilAndBooleanCheckSpecialization::isNilCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(' === null)', $loc);
        return true;
    }

    /**
     * `(some? x)` 1-arg — emit `($x !== null)` directly. The 2-arg
     * overload `(some? pred coll)` keeps the runtime dispatch.
     */
    private function tryEmitSomeCheck(CallNode $node): bool
    {
        if (!NilAndBooleanCheckSpecialization::isSomeCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(' !== null)', $loc);
        return true;
    }

    private function tryEmitTrueCheck(CallNode $node): bool
    {
        if (!NilAndBooleanCheckSpecialization::isTrueCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(' === true)', $loc);
        return true;
    }

    private function tryEmitFalseCheck(CallNode $node): bool
    {
        if (!NilAndBooleanCheckSpecialization::isFalseCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(' === false)', $loc);
        return true;
    }

    /**
     * `(truthy? x)` — Phel-truthy probe inlined. Uses a fresh `$__truthy`
     * binding so the result is a bool the caller can splice into any
     * expression position.
     */
    private function tryEmitTruthyCheck(CallNode $node): bool
    {
        if (!NilAndBooleanCheckSpecialization::isTruthyCheck($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(($__truthy = ', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(') !== null && $__truthy !== false)', $loc);
        return true;
    }
}
