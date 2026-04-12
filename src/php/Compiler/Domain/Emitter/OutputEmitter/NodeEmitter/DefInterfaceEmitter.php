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

        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            $this->emitViaEval($node);
        } else {
            $this->emitInline($node);
        }
    }

    /**
     * In statement mode, the interface definition may end up inside a function
     * wrapper. PHP forbids namespace declarations inside functions, so we
     * capture the interface body at compile time and emit it as an eval() call.
     */
    private function emitViaEval(DefInterfaceNode $node): void
    {
        $ns = $this->outputEmitter->mungeEncodeNs($node->getNamespace());
        $fqcn = $ns . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());

        ob_start();
        $this->emitInterfaceBody($node);
        $interfaceBody = (string) ob_get_clean();

        $this->outputEmitter->emitLine("if (!interface_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $evalCode = 'namespace ' . $ns . ";\n" . $interfaceBody;
        $this->outputEmitter->emitLine(
            'eval(' . var_export($evalCode, true) . ');',
            $node->getStartSourceLocation(),
        );

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    /**
     * In file/cache mode the NsEmitter already declared the namespace.
     */
    private function emitInline(DefInterfaceNode $node): void
    {
        $fqcn = $this->outputEmitter->mungeEncodeNs($node->getNamespace())
            . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());
        $this->outputEmitter->emitLine("if (!interface_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());

        $this->emitInterfaceBody($node);

        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    private function emitInterfaceBody(DefInterfaceNode $node): void
    {
        $this->outputEmitter->emitLine(
            'interface ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' {',
            $node->getStartSourceLocation(),
        );
        $this->outputEmitter->increaseIndentLevel();

        foreach ($node->getMethods() as $defInterfaceMethod) {
            $this->emitMethod($node, $defInterfaceMethod);
        }

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
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
}
