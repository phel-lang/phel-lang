<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\CallNode;
use Phel\Compiler\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class CallEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof CallNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            $this->emitPhpVarNodeInfix($node, $fnNode);
        } else {
            $this->emitPhpVarNode($node, $fnNode);
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }

    private function emitPhpVarNodeInfix(CallNode $node, PhpVarNode $fnNode): void
    {
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitArgList(
            $node->getArguments(),
            $node->getStartSourceLocation(),
            ' ' . $fnNode->getName() . ' '
        );
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function emitPhpVarNode(CallNode $node, AbstractNode $fnNode): void
    {
        if ($fnNode instanceof PhpVarNode) {
            $this->outputEmitter->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
        } else {
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($node->getFn());
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitArgList($node->getArguments(), $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }
}
