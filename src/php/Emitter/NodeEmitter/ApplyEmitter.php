<?php

declare(strict_types=1);

namespace Phel\Emitter\NodeEmitter;

use Phel\Ast\ApplyNode;
use Phel\Ast\Node;
use Phel\Ast\PhpVarNode;
use Phel\Emitter\NodeEmitter;

final class ApplyEmitter implements NodeEmitter
{
    use WithEmitter;

    public function emit(Node $node): void
    {
        assert($node instanceof ApplyNode);
        $this->emitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            $this->emitter->emitStr('array_reduce([', $node->getStartSourceLocation());
            // Args
            $argCount = count($node->getArguments());
            foreach ($node->getArguments() as $i => $arg) {
                if ($i < $argCount - 1) {
                    $this->emitter->emitNode($arg);
                    $this->emitter->emitStr(', ', $node->getStartSourceLocation());
                } else {
                    $this->emitter->emitStr('...((', $node->getStartSourceLocation());
                    $this->emitter->emitNode($arg);
                    $this->emitter->emitStr(') ?? [])', $node->getStartSourceLocation());
                }
            }
            $this->emitter->emitStr('], function($a, $b) { return ($a ', $node->getStartSourceLocation());
            $this->emitter->emitStr($fnNode->getName(), $fnNode->getStartSourceLocation());
            $this->emitter->emitStr(' $b); })', $node->getStartSourceLocation());
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
            $argCount = count($node->getArguments());
            foreach ($node->getArguments() as $i => $arg) {
                if ($i < $argCount - 1) {
                    $this->emitter->emitNode($arg);
                    $this->emitter->emitStr(', ', $node->getStartSourceLocation());
                } else {
                    $this->emitter->emitStr('...((', $node->getStartSourceLocation());
                    $this->emitter->emitNode($arg);
                    $this->emitter->emitStr(') ?? [])', $node->getStartSourceLocation());
                }
            }
            $this->emitter->emitStr(')', $node->getStartSourceLocation());
        }

        $this->emitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
