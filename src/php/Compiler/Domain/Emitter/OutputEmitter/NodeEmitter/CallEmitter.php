<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;

use function assert;
use function count;

final class CallEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof CallNode);

        $fnNode = $node->getFn();
        $isYield = $fnNode instanceof PhpVarNode && $fnNode->getName() === 'yield';

        if (!$isYield) {
            $this->emitContextPrefix($node);
        }

        if ($fnNode instanceof PhpVarNode && $fnNode->isInfix()) {
            if ($fnNode->getName() === 'instanceof' && count($node->getArguments()) === 2) {
                $this->emitInstanceof($node);
            } else {
                $this->emitPhpVarNodeInfix($node, $fnNode);
            }
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
        $arguments = $node->getArguments();
        $argumentCount = count($arguments);
        foreach ($arguments as $i => $argument) {
            $this->emitInfixArgument($fnNode, $argument, $i);

            if ($i < $argumentCount - 1) {
                $this->outputEmitter->emitStr(' ' . $fnNode->getName() . ' ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
    }

    private function emitInstanceof(CallNode $node): void
    {
        $arguments = $node->getArguments();
        assert(count($arguments) === 2);
        [$value, $class] = $arguments;

        if ($class instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($value);
            $this->outputEmitter->emitStr(' instanceof ', $node->getStartSourceLocation());
            $this->outputEmitter->emitStr($class->getAbsolutePhpName(), $class->getName()->getStartLocation());
            $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
            return;
        }

        $valueSym = Symbol::gen('instanceof_value_');
        $classSym = Symbol::gen('instanceof_class_');

        $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitPhpVariable($valueSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($value);
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitPhpVariable($classSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($class);
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitStr('return ', $node->getStartSourceLocation());
        $this->outputEmitter->emitPhpVariable($valueSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' instanceof ', $node->getStartSourceLocation());
        $this->outputEmitter->emitPhpVariable($classSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());
    }

    private function emitInfixArgument(PhpVarNode $fnNode, AbstractNode $argument, int $index): void
    {
        if ($fnNode->getName() === 'instanceof' && $index === 1 && $argument instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr($argument->getAbsolutePhpName(), $argument->getName()->getStartLocation());
            return;
        }

        $this->outputEmitter->emitNode($argument);
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
        $name = $fnNode->getAbsoluteName();

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
