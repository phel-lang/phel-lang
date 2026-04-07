<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class PhpVarEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpVarNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $name = $node->getAbsoluteName();

        if ($node->isCallable()) {
            $this->outputEmitter->emitStr(
                '(function(...$args) { return ' . $name . '(...$args);' . '})',
                $node->getStartSourceLocation(),
            );
        } else {
            $this->outputEmitter->emitStr($name, $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
