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
        $suffix = NilAndBooleanCheckSpecialization::identityCheckSuffix($node);
        if ($suffix !== null) {
            $loc = $node->getStartSourceLocation();
            $this->outputEmitter->emitStr('(', $loc);
            $this->outputEmitter->emitNode($node->getArguments()[0]);
            $this->outputEmitter->emitStr($suffix, $loc);
            return true;
        }

        return $this->tryEmitTruthyCheck($node);
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
