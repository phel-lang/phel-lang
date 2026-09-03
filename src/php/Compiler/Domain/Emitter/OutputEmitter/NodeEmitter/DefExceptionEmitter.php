<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\DefExceptionNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Shared\PhpAttributeRenderer;

use function assert;

/**
 * @internal
 */
final readonly class DefExceptionEmitter implements NodeEmitterInterface
{
    use EvalGuardedEmitterTrait;
    use PhpAttributeEmitterTrait;

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
        private PhpAttributeRenderer $attributeRenderer = new PhpAttributeRenderer(),
    ) {}

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof DefExceptionNode);

        $this->emitGuardedTypeDeclaration(
            $node->getNamespace(),
            $node->getName()->getName(),
            'class_exists',
            $node->getStartSourceLocation(),
            function () use ($node): void {
                $this->emitClassBody($node);
            },
        );
    }

    private function emitClassBody(DefExceptionNode $node): void
    {
        $this->emitAttributes($node->getName()->getMeta(), $node->getStartSourceLocation());

        $this->outputEmitter->emitStr(
            'class ' . $this->outputEmitter->mungeEncode($node->getName()->getName()) . ' extends ',
            $node->getStartSourceLocation(),
        );
        $parent = $node->getParent();
        $this->outputEmitter->emitStr($parent->getAbsolutePhpName(), $parent->getName()->getStartLocation());
        $this->outputEmitter->emitLine(' {');
        $this->outputEmitter->increaseIndentLevel();

        $this->outputEmitter->emitLine('public function __construct($message = "", $code = 0, ?\Throwable $previous = null) {');
        $this->outputEmitter->increaseIndentLevel();
        $this->outputEmitter->emitLine('parent::__construct($message, $code, $previous);');
        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}');

        $this->outputEmitter->decreaseIndentLevel();
        $this->outputEmitter->emitLine('}', $node->getStartSourceLocation());
    }
}
