<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefEnumNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Shared\PhpAttributeRenderer;

use function assert;
use function var_export;

/**
 * @internal
 */
final readonly class DefEnumEmitter implements NodeEmitterInterface
{
    use EvalGuardedEmitterTrait;
    use PhpAttributeEmitterTrait;

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private MethodEmitter $methodEmitter,
        private PhpAttributeRenderer $attributeRenderer = new PhpAttributeRenderer(),
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefEnumNode);

        $this->emitGuardedTypeDeclaration(
            $node->getNamespace(),
            $node->getName()->getName(),
            'enum_exists',
            $node->getStartSourceLocation(),
            function () use ($node): void {
                $this->emitEnumBody($node);
            },
        );
    }

    private function emitEnumBody(DefEnumNode $node): void
    {
        $this->emitAttributes($node->getName()->getMeta(), $node->getStartSourceLocation());

        $header = 'enum ' . $this->outputEmitter->mungeEncode($node->getName()->getName());
        $backingType = $node->getBackingType();
        if ($backingType !== null) {
            $header .= ': ' . $backingType;
        }

        $interfaceNames = $this->interfaceNames($node);
        if ($interfaceNames !== []) {
            $header .= ' implements ' . implode(', ', $interfaceNames);
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

        $this->emitMethods($node);

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }

    /**
     * The `implements` list: every named interface declared on the enum. A
     * `:php` block carries an empty name and adds no `implements` entry.
     *
     * @return list<string>
     */
    private function interfaceNames(DefEnumNode $node): array
    {
        $names = [];
        foreach ($node->getInterfaces() as $interface) {
            if ($interface->getAbsoluteInterfaceName() !== '') {
                $names[] = $interface->getAbsoluteInterfaceName();
            }
        }

        return $names;
    }

    private function emitMethods(DefEnumNode $node): void
    {
        foreach ($node->getInterfaces() as $interface) {
            foreach ($interface->getMethods() as $method) {
                $this->outputEmitter->emitLine();
                $this->emitAttributes($method->getName()->getMeta(), $node->getStartSourceLocation());
                $this->methodEmitter->emit($method->getName()->getName(), $method->getFnNode());
            }
        }
    }
}
