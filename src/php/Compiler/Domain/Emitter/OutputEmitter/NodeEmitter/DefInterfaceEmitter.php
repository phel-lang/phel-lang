<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceMethod;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

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
                $node->getStartSourceLocation(),
            );
        }

        $this->outputEmitter->emitLine(
            'interface ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' {',
            $node->getStartSourceLocation(),
        );
        $this->outputEmitter->increaseIndentLevel();
    }

    private function emitMethods(DefInterfaceNode $node): void
    {
        foreach ($node->getMethods() as $defInterfaceMethod) {
            $this->emitMethod($node, $defInterfaceMethod);
        }
    }

    private function emitMethod(DefInterfaceNode $node, DefInterfaceMethod $method): void
    {
        $this->outputEmitter->emitStr('public function ', $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(
            $this->outputEmitter->mungeEncode($method->getName()->getName()),
            $node->getStartSourceLocation(),
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
