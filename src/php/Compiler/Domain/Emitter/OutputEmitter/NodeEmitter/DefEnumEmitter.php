<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefEnumNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\SourceLocation;
use Phel\Shared\PhpAttributeRenderer;

use function assert;
use function var_export;

final readonly class DefEnumEmitter implements NodeEmitterInterface
{
    use PhpAttributeEmitterTrait;

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private PhpAttributeRenderer $attributeRenderer = new PhpAttributeRenderer(),
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefEnumNode);

        if ($this->shouldEmitViaEval()) {
            $this->emitViaEval($node);
        } else {
            $this->emitInline($node);
        }
    }

    /**
     * The enum declaration must be lifted into an `eval()` when it would
     * otherwise land somewhere PHP forbids: inside a function wrapper in
     * statement mode (namespace not allowed), or inside another class's body.
     */
    private function shouldEmitViaEval(): bool
    {
        if ($this->outputEmitter->getOptions()->isStatementEmitMode()) {
            return true;
        }

        return $this->outputEmitter->isInsideClassScope();
    }

    private function emitViaEval(DefEnumNode $node): void
    {
        $ns = $this->outputEmitter->mungeEncodePhpNs($node->getNamespace());
        $fqcn = $ns . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());

        ob_start();
        $this->emitEnumBody($node);
        $enumBody = (string) ob_get_clean();

        $this->outputEmitter->emitLine("if (!enum_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        $evalCode = 'namespace ' . $ns . ";\n" . $enumBody;
        $this->outputEmitter->emitLine(
            'eval(' . var_export($evalCode, true) . ');',
            $node->getStartSourceLocation(),
        );

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    /**
     * In file/cache mode the NsEmitter already declared the namespace at the
     * top of the file, so the enum is emitted inline.
     */
    private function emitInline(DefEnumNode $node): void
    {
        $fqcn = $this->outputEmitter->mungeEncodePhpNs($node->getNamespace())
            . '\\' . $this->outputEmitter->mungeEncode($node->getName()->getName());
        $this->outputEmitter->emitLine("if (!enum_exists('" . $fqcn . "')) {", $node->getStartSourceLocation());

        $this->emitEnumBody($node);

        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    private function emitEnumBody(DefEnumNode $node): void
    {
        $this->emitAttributes($node->getName()->getMeta(), $node->getStartSourceLocation());

        $header = 'enum ' . $this->outputEmitter->mungeEncode($node->getName()->getName());
        $backingType = $node->getBackingType();
        if ($backingType !== null) {
            $header .= ': ' . $backingType;
        }

        $this->outputEmitter->emitLine($header . ' {', $node->getStartSourceLocation());
        $this->outputEmitter->increaseIndentLevel();

        foreach ($node->getCases() as $case) {
            $line = 'case ' . $this->outputEmitter->mungeEncode($case->getName());
            $value = $case->getValue();
            if ($value !== null) {
                $line .= ' = ' . var_export($value, true);
            }

            $this->outputEmitter->emitLine($line . ';', $node->getStartSourceLocation());
        }

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    /**
     * Emits one `#[...]` line per `:php/attr` spec carried by the enum name
     * meta, at the current indentation, before the enum declaration.
     *
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function emitAttributes(?PersistentMapInterface $meta, ?SourceLocation $sourceLocation): void
    {
        foreach ($this->phpAttributeLines($this->attributeRenderer, $meta) as $attribute) {
            $this->outputEmitter->emitLine($attribute, $sourceLocation);
        }
    }
}
