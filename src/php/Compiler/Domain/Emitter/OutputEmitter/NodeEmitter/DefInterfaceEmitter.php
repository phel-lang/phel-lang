<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceMethod;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\SourceLocation;
use Phel\Shared\PhpAttributeRenderer;

use function assert;

final readonly class DefInterfaceEmitter implements NodeEmitterInterface
{
    use PhpAttributeEmitterTrait;

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private PhpAttributeRenderer $attributeRenderer = new PhpAttributeRenderer(),
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefInterfaceNode);

        if ($this->shouldEmitViaEval()) {
            $this->emitViaEval($node);
        } else {
            $this->emitInline($node);
        }
    }

    private function shouldEmitViaEval(): bool
    {
        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            return true;
        }

        return $this->outputEmitter->isInsideClassScope();
    }

    /**
     * Captures the interface body at compile time and emits it as an
     * `eval()` call guarded by `interface_exists`. Needed both in
     * statement mode and when we are inside another class's method body
     * (where PHP rejects nested declarations).
     */
    private function emitViaEval(DefInterfaceNode $node): void
    {
        $ns = $this->outputEmitter->mungeEncodePhpNs($node->getNamespace());
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
        $fqcn = $this->outputEmitter->mungeEncodePhpNs($node->getNamespace())
            . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());
        $this->outputEmitter->emitLine("if (!interface_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());

        $this->emitInterfaceBody($node);

        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    private function emitInterfaceBody(DefInterfaceNode $node): void
    {
        $sourceLocation = $node->getStartSourceLocation();
        $this->emitAttributes($node->getName()->getMeta(), $sourceLocation);

        $this->outputEmitter->emitLine(
            'interface ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' {',
            $sourceLocation,
        );
        $this->outputEmitter->increaseIndentLevel();

        foreach ($node->getMethods() as $defInterfaceMethod) {
            $this->emitMethod($node, $defInterfaceMethod);
        }

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $sourceLocation);
    }

    private function emitMethod(DefInterfaceNode $node, DefInterfaceMethod $method): void
    {
        $sourceLocation = $node->getStartSourceLocation();
        $this->emitAttributes($method->getName()->getMeta(), $sourceLocation);

        $this->outputEmitter->emitStr('public function ', $sourceLocation);
        $this->outputEmitter->emitStr(
            $this->outputEmitter->mungeEncode($method->getName()->getName()),
            $sourceLocation,
        );
        $this->outputEmitter->emitStr('(', $sourceLocation);

        foreach ($method->getArgumentsWithoutFirst() as $i => $argument) {
            $argumentTag = $this->tagTypeFromMeta($argument->getMeta());
            if ($argumentTag !== null) {
                $this->outputEmitter->emitStr($argumentTag . ' ', $sourceLocation);
            }

            $this->outputEmitter->emitPhpVariable($argument, $sourceLocation);

            if ($i < $method->getArgumentCount() - 2) {
                $this->outputEmitter->emitStr(', ', $sourceLocation);
            }
        }

        $this->outputEmitter->emitStr(')', $sourceLocation);

        $returnTag = $this->tagTypeFromMeta($method->getName()->getMeta());
        if ($returnTag !== null) {
            $this->outputEmitter->emitStr(': ' . $returnTag, $sourceLocation);
        }

        $this->outputEmitter->emitLine(';');
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function emitAttributes(?PersistentMapInterface $meta, ?SourceLocation $sourceLocation): void
    {
        foreach ($this->phpAttributeLines($this->attributeRenderer, $meta) as $attribute) {
            $this->outputEmitter->emitLine($attribute, $sourceLocation);
        }
    }
}
