<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpRefNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

/**
 * Emits a `(php/ref local)` argument as the plain `$local` variable. PHP forbids
 * call-time pass-by-reference (`f(&$x)`), so by-ref is realised by capturing
 * the local with `use(&$local)` in the wrapping closure (see
 * `PhpObjectCallEmitter` and `OutputEmitter::emitFnWrapPrefix`).
 */
final class PhpRefEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpRefNode);

        $this->outputEmitter->emitPhpVariable($node->getName(), $node->getStartSourceLocation());
    }
}
