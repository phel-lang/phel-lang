<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Ast\CallNode;
use Phel\Compiler\Ast\Node;
use Phel\Compiler\Ast\PhpVarNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

final class CallEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof CallNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            // Args
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitArgList(
                $node->getArguments(),
                $node->getStartSourceLocation(),
                ' ' . $fnNode->getName() . ' '
            );
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        } else {
            if ($fnNode instanceof PhpVarNode) {
                $this->outputEmitter->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
            } else {
                $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
                $this->outputEmitter->emitNode($node->getFn());
                $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
            }

            // Args
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitArgList($node->getArguments(), $node->getStartSourceLocation());
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
