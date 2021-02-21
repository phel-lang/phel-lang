<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;

final class PhpObjectSetEmitter implements NodeEmitterInterface
{
    use WithOutputEmitterTrait;

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpObjectSetNode);

        $fnCode = $node->getLeftExpr()->isStatic() ? '::' : '->';
        $targetExpr = $node->getLeftExpr()->getTargetExpr();
        $callExpr = $node->getLeftExpr()->getCallExpr();
        assert($callExpr instanceof PropertyOrConstantAccessNode);

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitFnWrapPrefix($node->getEnv(), $node->getStartSourceLocation());

        $targetSym = Symbol::gen('target_');
        $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($targetExpr);
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr($fnCode, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr($callExpr->getName()->getName(), $callExpr->getName()->getStartLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getRightExpr());
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitStr('return ', $node->getStartSourceLocation());
        $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

        $this->outputEmitter->emitFnWrapSuffix($node->getStartSourceLocation());

        $this->outputEmitter->emitContextSuffix($node->getEnv(), $node->getStartSourceLocation());
    }
}
