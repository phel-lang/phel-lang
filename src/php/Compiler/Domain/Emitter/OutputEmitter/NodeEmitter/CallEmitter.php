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

        $this->emitContextPrefix($node);
        $fnNode = $node->getFn();

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            $this->emitPhpVarNodeInfix($node, $fnNode);
        } else {
            $this->emitPhpVarNode($node, $fnNode);
        }

        $this->emitContextSuffix($node);
    }

    private function emitContextPrefix(CallNode $node): void
    {
        $this->outputEmitter->emitContextPrefix(
            $node->getEnv(),
            $node->getStartSourceLocation(),
        );
    }

    private function emitContextSuffix(CallNode $node): void
    {
        $this->outputEmitter->emitContextSuffix(
            $node->getEnv(),
            $node->getStartSourceLocation(),
        );
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
            if ($fnNode->getName() === 'yield') {
                $this->emitYieldArguments($node, $fnNode);
                return;
            }

            $this->emitPhpFunctionName($fnNode);
        } else {
            $this->emitDynamicFunctionName($node);
        }

        $this->emitFunctionArguments($node);
    }

    private function emitPhpFunctionName(PhpVarNode $fnNode): void
    {
        $name = $fnNode->getName();

        if ($name === 'echo') {
            $name = 'print';
        }

        $this->outputEmitter->emitStr($name, $fnNode->getStartSourceLocation());
    }

    private function emitYieldArguments(CallNode $node, PhpVarNode $fnNode): void
    {
        $this->outputEmitter->emitStr('yield', $fnNode->getStartSourceLocation());

        $args = $node->getArguments();
        $argsCount = count($args);
        if ($argsCount > 0) {
            $this->outputEmitter->emitStr(' ', $fnNode->getStartSourceLocation());
            $this->outputEmitter->emitNode($args[0]);

            if ($argsCount === 2) {
                $this->outputEmitter->emitStr(' => ', $fnNode->getStartSourceLocation());
                $this->outputEmitter->emitNode($args[1]);
            }
        }
    }

    private function emitDynamicFunctionName(CallNode $node): void
    {
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getFn());
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function emitFunctionArguments(CallNode $node): void
    {
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
        $this->outputEmitter->emitArgList(
            $node->getArguments(),
            $node->getStartSourceLocation(),
        );
        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }
}
