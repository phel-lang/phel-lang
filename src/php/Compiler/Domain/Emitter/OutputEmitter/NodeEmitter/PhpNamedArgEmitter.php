<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpNamedArgNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

/**
 * Emits a `(... :& :name value)` argument as a PHP 8 named argument
 * `name: <value>`. The parameter name is emitted verbatim, so the keyword
 * must match the PHP parameter name exactly (like interop method names).
 */
final class PhpNamedArgEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpNamedArgNode);

        $this->outputEmitter->emitStr($node->getName() . ': ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getValueExpr());
    }
}
