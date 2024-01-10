<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LetNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

final class LetEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof LetNode);

        $isWrapFn = $node->getEnv()->isContext(NodeEnvironment::CONTEXT_EXPRESSION);
        if ($isWrapFn) {
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        foreach ($node->getBindings() as $bindingNode) {
            $this->outputEmitter->emitPhpVariable($bindingNode->getShadow(), $bindingNode->getStartSourceLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($bindingNode->getInitExpr());
            $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
        }

        if ($node->isLoop()) {
            $this->outputEmitter->emitLine('while (true) {', $node->getStartSourceLocation());
            $this->outputEmitter->increaseIndentLevel();
        }

        $this->outputEmitter->emitNode($node->getBodyExpr());

        if ($node->isLoop()) {
            $this->outputEmitter->emitLine('break;', $node->getStartSourceLocation());
            $this->outputEmitter->decreaseIndentLevel();
            $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());
        }

        if ($isWrapFn) {
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
        }
    }
}
