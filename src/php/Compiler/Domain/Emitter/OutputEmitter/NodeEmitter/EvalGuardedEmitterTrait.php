<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

/**
 * Shared decision for emitters that generate a top-level PHP type declaration
 * (`defstruct`, `defexception`, `defenum`). The declaration must be lifted into
 * an `eval()` when it would otherwise land somewhere PHP forbids: inside a
 * function wrapper in statement mode (namespace not allowed), or inside another
 * class's method body, e.g. used inside a `deftest` body.
 *
 * Using emitters must expose the `$outputEmitter` property (as every node
 * emitter already does).
 *
 * @property-read OutputEmitterInterface $outputEmitter
 */
trait EvalGuardedEmitterTrait
{
    private function shouldEmitViaEval(): bool
    {
        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            return true;
        }

        return $this->outputEmitter->isInsideClassScope();
    }
}
