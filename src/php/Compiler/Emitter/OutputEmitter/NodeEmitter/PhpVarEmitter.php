<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class PhpVarEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpVarNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        if ($node->isCallable()) {
            $this->outputEmitter->emitStr(
                '(function(...$args) { return ' . $node->getName() . '(...$args);' . '})',
                $node->getStartSourceLocation()
            );
        } else {
            $this->outputEmitter->emitStr($node->getName(), $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
