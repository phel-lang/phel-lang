<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\ApplyNode;
use Phel\Ast\Node;
use Phel\Ast\PhpVarNode;
use Phel\Emitter\NodeEmitter;

final class ApplyEmitter implements NodeEmitter
{
    use WithOutputEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof ApplyNode);
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            $this->outputEmitter->emitStr('array_reduce([', $node->getStartSourceLocation());
            // Args
            $argCount = count($node->getArguments());
            foreach ($node->getArguments() as $i => $arg) {
                if ($i < $argCount - 1) {
                    $this->outputEmitter->emitNode($arg);
                    $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
                } else {
                    $this->outputEmitter->emitStr('...((', $node->getStartSourceLocation());
                    $this->outputEmitter->emitNode($arg);
                    $this->outputEmitter->emitStr(') ?? [])', $node->getStartSourceLocation());
                }
            }
            $this->outputEmitter->emitStr('], function($a, $b) { return ($a ', $node->getStartSourceLocation());
            $this->outputEmitter->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
            $this->outputEmitter->emitStr(' $b); })', $node->getStartSourceLocation());
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
            $argCount = count($node->getArguments());
            foreach ($node->getArguments() as $i => $arg) {
                if ($i < $argCount - 1) {
                    $this->outputEmitter->emitNode($arg);
                    $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
                } else {
                    $this->outputEmitter->emitStr('...((', $node->getStartSourceLocation());
                    $this->outputEmitter->emitNode($arg);
                    $this->outputEmitter->emitStr(') ?? [])', $node->getStartSourceLocation());
                }
            }
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        }

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
