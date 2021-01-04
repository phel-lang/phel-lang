<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\ForeachNode;
use Phel\Compiler\Analyzer\Environment\NodeEnvironmentInterface;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class ForeachEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof ForeachNode);

        if ($node->getEnv()->getContext() !== NodeEnvironmentInterface::CONTEXT_STATEMENT) {
            $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitStr('foreach ((', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getListExpr());
        $this->outputEmitter->emitStr(' ?? []) as ', $node->getStartSourceLocation());
        if ($node->getKeySymbol()) {
            $this->outputEmitter->emitPhpVariable($node->getKeySymbol());
            $this->outputEmitter->emitStr(' => ', $node->getStartSourceLocation());
        }
        $this->outputEmitter->emitPhpVariable($node->getValueSymbol());
        $this->outputEmitter->emitLine(') {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitNode($node->getBodyExpr());
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine();
        $this->outputEmitter->emitStr('}', $node->getStartSourceLocation());

        if ($node->getEnv()->getContext() !== NodeEnvironmentInterface::CONTEXT_STATEMENT) {
            $this->outputEmitter->emitLine();
            $this->outputEmitter->emitStr('return null;', $node->getStartSourceLocation());
            $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
            $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
        }
    }
}
