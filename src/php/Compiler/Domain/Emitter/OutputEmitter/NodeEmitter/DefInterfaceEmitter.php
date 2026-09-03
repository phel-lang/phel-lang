<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceMethod;
use Phel\Compiler\Domain\Analyzer\Ast\DefInterfaceNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassConst;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Shared\PhpAttributeRenderer;

use function assert;
use function var_export;

/**
 * @internal
 */
final readonly class DefInterfaceEmitter implements NodeEmitterInterface
{
    use EvalGuardedEmitterTrait;
    use PhpAttributeEmitterTrait;

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private PhpAttributeRenderer $attributeRenderer = new PhpAttributeRenderer(),
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefInterfaceNode);

        $this->emitGuardedTypeDeclaration(
            $node->getNamespace(),
            $node->getName()->getName(),
            'interface_exists',
            $node->getStartSourceLocation(),
            function () use ($node): void {
                $this->emitInterfaceBody($node);
            },
        );
    }

    private function emitInterfaceBody(DefInterfaceNode $node): void
    {
        $sourceLocation = $node->getStartSourceLocation();
        $this->emitDocBlock($node->getName()->getMeta(), $sourceLocation);
        $this->emitAttributes($node->getName()->getMeta(), $sourceLocation);

        $this->outputEmitter->emitLine(
            'interface ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' {',
            $sourceLocation,
        );
        $this->outputEmitter->increaseIndentLevel();

        foreach ($node->getConsts() as $const) {
            $this->emitConst($node, $const);
        }

        foreach ($node->getMethods() as $defInterfaceMethod) {
            $this->emitMethod($node, $defInterfaceMethod);
        }

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $sourceLocation);
    }

    private function emitConst(DefInterfaceNode $node, PhpClassConst $const): void
    {
        $sourceLocation = $node->getStartSourceLocation();
        $this->emitDocBlock($const->getName()->getMeta(), $sourceLocation);
        $this->emitAttributes($const->getName()->getMeta(), $sourceLocation);

        $line = 'const ';
        $tag = $this->tagTypeFromMeta($const->getName()->getMeta());
        if ($tag !== null) {
            $line .= $tag . ' ';
        }

        $line .= $this->outputEmitter->mungeEncode($const->getName()->getName())
            . ' = ' . var_export($const->getValue(), true) . ';';

        $this->outputEmitter->emitLine($line, $sourceLocation);
    }

    private function emitMethod(DefInterfaceNode $node, DefInterfaceMethod $method): void
    {
        $sourceLocation = $node->getStartSourceLocation();
        $this->emitDocBlock($method->getName()->getMeta(), $sourceLocation);
        $this->emitAttributes($method->getName()->getMeta(), $sourceLocation);

        $this->outputEmitter->emitStr('public function ', $sourceLocation);
        $this->outputEmitter->emitStr(
            $this->outputEmitter->mungeEncode($method->getName()->getName()),
            $sourceLocation,
        );
        $this->outputEmitter->emitStr('(', $sourceLocation);

        foreach ($method->getArgumentsWithoutFirst() as $i => $argument) {
            foreach ($this->phpAttributeLines($argument->getMeta()) as $attribute) {
                $this->outputEmitter->emitStr($attribute . ' ', $sourceLocation);
            }

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
}
