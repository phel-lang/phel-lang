<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ByRefLocalCollector;
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

        // `php/oset` is contractually required to evaluate to the *target
        // object* (not the assigned value, as a bare PHP `$o->p = v` would
        // yield). Whenever the value is consumed - expression or return
        // context - we must host a temp + `return $target` inside an IIFE to
        // preserve that semantics. In statement context the value is
        // discarded, so we emit the assignment directly with no closure and
        // no temp: the target is still evaluated exactly once inline.
        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            $this->outputEmitter->emitNode($targetExpr);
            $this->outputEmitter->emitStr($fnCode, $node->getStartSourceLocation());
            $this->outputEmitter->emitStr($callExpr->getName()->getName(), $callExpr->getName()->getStartLocation());
            $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
            $this->outputEmitter->emitNode($node->getRightExpr());
            $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

            return;
        }

        $this->outputEmitter->emitContextPrefix($node->getEnv(), $node->getStartSourceLocation());

        $this->outputEmitter->emitFnWrapPrefix(
            $node->getEnv(),
            $node->getStartSourceLocation(),
            new ByRefLocalCollector()->collect($node),
        );

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
