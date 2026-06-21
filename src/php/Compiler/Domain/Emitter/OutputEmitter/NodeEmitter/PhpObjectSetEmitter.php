<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ContextualWrapEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Lang\Symbol;

use function assert;

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
        $propertyName = $callExpr->getName()->getName();
        $propertyLoc = $callExpr->getName()->getStartLocation();

        // `php/oset` is contractually required to evaluate to the *target
        // object* (not the assigned value, as a bare PHP `$o->p = v` would
        // yield). In statement context the value is discarded, so we emit the
        // assignment directly with no closure and no temp: the target is still
        // evaluated exactly once inline.
        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            $this->outputEmitter->emitNode($targetExpr);
            $this->outputEmitter->emitStr($fnCode, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr($propertyName, $propertyLoc);
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($node->getRightExpr());
            $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

            return;
        }

        // Expression or return context: the value is consumed, so we host a
        // temp and yield `$target` to preserve the "returns the target object"
        // semantics. The shared kernel wraps it in an IIFE only in expression
        // context; in return context it elides the closure and emits plain
        // statements ending in `return $target;`.
        $targetSym = Symbol::gen('target_');

        new ContextualWrapEmitter($this->outputEmitter)->emit(
            $node,
            function () use ($node, $targetExpr, $fnCode, $propertyName, $propertyLoc, $targetSym): void {
                $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
                $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
                $this->outputEmitter->emitNode($targetExpr);
                $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

                $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
                $this->outputEmitter->emitStr($fnCode, $node->getStartSourceLocation());
                $this->outputEmitter->emitStr($propertyName, $propertyLoc);
                $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
                $this->outputEmitter->emitNode($node->getRightExpr());
                $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
            },
            function () use ($node, $targetSym): void {
                $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            },
        );
    }
}
