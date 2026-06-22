<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\AtomMethodSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

/**
 * Specialisation gated by {@see AtomMethodSpecialization}: `(deref x)` and
 * `(reset! v val)` lowered to the direct method call on the target, skipping
 * the registry lookup.
 */
final readonly class AtomMethodCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    /**
     * `(deref x)` / `(reset! v val)` — emit the direct method call on
     * the target, skipping the registry lookup. No tag required: the
     * runtime body itself is a single `php/-> target (method ...)`, so
     * the failure mode (method not found) is identical to today.
     */
    public function tryEmit(CallNode $node): bool
    {
        $shape = AtomMethodSpecialization::atomMethodCall($node);
        if ($shape === null) {
            return false;
        }

        [$method, $argIndices] = $shape;
        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();

        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $method . '(', $loc);
        foreach ($argIndices as $i => $idx) {
            if ($i > 0) {
                $this->outputEmitter->emitStr(', ', $loc);
            }

            $this->outputEmitter->emitNode($args[$idx]);
        }

        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }
}
