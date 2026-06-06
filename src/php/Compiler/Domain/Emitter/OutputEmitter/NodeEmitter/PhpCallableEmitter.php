<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpCallableNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;

use function assert;

/**
 * Emits a native PHP 8.1 first-class callable `(...)`:
 *
 * - free function:    `\strlen(...)`
 * - static method:    `\Foo::bar(...)`
 * - instance method:  `($obj)->process(...)`.
 */
final class PhpCallableEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpCallableNode);

        $location = $node->getStartSourceLocation();
        $this->outputEmitter->emitContextPrefix($node->getEnv(), $location);
        $this->outputEmitter->emitStr('(', $location);
        $this->emitCallableReference($node);
        $this->outputEmitter->emitStr('(...))', $location);
        $this->outputEmitter->emitContextSuffix($node->getEnv(), $location);
    }

    private function emitCallableReference(PhpCallableNode $node): void
    {
        $location = $node->getStartSourceLocation();
        $targetExpr = $node->getTargetExpr();

        if (!$targetExpr instanceof AbstractNode) {
            $this->outputEmitter->emitStr($node->getName(), $location);
            return;
        }

        if ($node->isStatic() && $targetExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr($targetExpr->getAbsolutePhpName() . '::' . $node->getName(), $location);
            return;
        }

        $this->outputEmitter->emitStr('(', $location);
        $this->outputEmitter->emitNode($targetExpr);
        $this->outputEmitter->emitStr(')->' . $node->getName(), $location);
    }
}
