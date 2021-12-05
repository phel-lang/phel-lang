<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\DefInterfaceMethod;
use Phel\Compiler\Analyzer\Ast\DefInterfaceNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;

final class DefInterfaceEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefInterfaceNode);

        $this->emitClassBegin($node);
        $this->emitMethods($node);
        $this->emitClassEnd($node);
    }

    private function emitClassBegin(DefInterfaceNode $node): void
    {
        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            $this->outputEmitter->emitLine(
                'namespace ' . $this->outputEmitter->mungeEncodeNs($node->getNamespace()) . ';',
                $node->getStartSourceLocation()
            );
        }
        $this->outputEmitter->emitLine(
            'interface ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' {',
            $node->getStartSourceLocation()
        );
        $this->outputEmitter->increaseIndentLevel();
    }

    private function emitMethods(DefInterfaceNode $node): void
    {
        foreach ($node->getMethods() as $method) {
            $this->emitMethod($node, $method);
        }
    }

    private function emitMethod(DefInterfaceNode $node, DefInterfaceMethod $method): void
    {
        $this->outputEmitter->emitStr('public function ', $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(
            $this->outputEmitter->mungeEncode($method->getName()->getName()),
            $node->getStartSourceLocation()
        );
        $this->outputEmitter->emitStr('(', $node->getStartSourceLocation());

        foreach ($method->getArgumentsWithoutFirst() as $i => $argument) {
            $this->outputEmitter->emitPhpVariable($argument, $node->getStartSourceLocation());

            if ($i < $method->getArgumentCount() - 2) {
                $this->outputEmitter->emitStr(', ', $node->getStartSourceLocation());
            }
        }

        $this->outputEmitter->emitStr(')', $node->getStartSourceLocation());
        $this->outputEmitter->emitLine(';');
    }

    private function emitClassEnd(DefInterfaceNode $node): void
    {
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
