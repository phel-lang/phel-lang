<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\CallNode;
use Phel\Ast\Node;
use Phel\Ast\PhpVarNode;
use Phel\Emitter;
use Phel\Emitter\NodeEmitter;

final class CallEmitter implements NodeEmitter
{
    private Emitter $emitter;

    public function __construct(Emitter $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(Node $node): void
    {
        assert($node instanceof CallNode);

        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());
        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            // Args
            $this->emitter->emitStr('(', $node->getStartSourceLocation());
            $this->emitter->emitArgList(
                $node->getArguments(),
                $node->getStartSourceLocation(),
                ' ' . $fnNode->getName() . ' '
            );
            $this->emitter->emitStr(')', $node->getStartSourceLocation());
        } else {
            if ($fnNode instanceof PhpVarNode) {
                $this->emitter->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
            } else {
                $this->emitter->emitStr('(', $node->getStartSourceLocation());
                $this->emitter->emitNode($node->getFn());
                $this->emitter->emitStr(')', $node->getStartSourceLocation());
            }

            // Args
            $this->emitter->emitStr('(', $node->getStartSourceLocation());
            $this->emitter->emitArgList($node->getArguments(), $node->getStartSourceLocation());
            $this->emitter->emitStr(')', $node->getStartSourceLocation());
        }

        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
