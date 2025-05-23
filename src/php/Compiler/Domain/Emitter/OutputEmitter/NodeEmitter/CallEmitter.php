<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;
use function count;

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
            ' ' . $fnNode->getName() . ' ',
        );
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function emitPhpVarNode(CallNode $node, AbstractNode $fnNode): void
    {
        if ($fnNode instanceof PhpVarNode) {
            $name = $fnNode->getName();
            // The only language structure that can be called like a function
            // and cannot be called using parentheses is `echo`.
            // For this reason, only for `echo` use `print` instead. #729
            if ($name === 'echo') {
                $name = 'print';
            }

            if ($name === 'yield') {
                $this->outputEmitter->emitStr('yield', $fnNode->getStartSourceLocation());

                $args = $node->getArguments();
                $argsCount = count($args);
                if ($argsCount > 0) {
                    $this->outputEmitter->emitStr(' ', $fnNode->getStartSourceLocation());
                    if ($argsCount === 1) {
                        $this->outputEmitter->emitNode($args[0]);
                    } else {
                        $this->outputEmitter->emitNode($args[0]);
                        $this->outputEmitter->emitStr(' => ', $fnNode->getStartSourceLocation());
                        $this->outputEmitter->emitNode($args[1]);
                    }
                }

                return;
            }

            $this->outputEmitter->emitStr($name, $fnNode->getStartSourceLocation());
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
