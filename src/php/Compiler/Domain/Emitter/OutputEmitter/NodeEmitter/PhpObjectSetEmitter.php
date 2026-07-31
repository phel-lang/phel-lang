<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpClassNameNode;
use Phel\Compiler\Domain\Analyzer\Ast\PhpObjectSetNode;
use Phel\Compiler\Domain\Analyzer\Ast\PropertyOrConstantAccessNode;
use Phel\Compiler\Domain\Analyzer\Environment\NodeEnvironment;
use Phel\Compiler\Domain\Emitter\OutputEmitter\ContextualWrapEmitter;
use Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitterInterface;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpObjectCallDispatch;
use Phel\Compiler\Domain\Emitter\OutputEmitter\PhpObjectCallDispatchResolver;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function assert;
use function str_starts_with;

/**
 * @internal
 */
final readonly class PhpObjectSetEmitter implements NodeEmitterInterface
{
    private PhpObjectCallDispatchResolver $dispatchResolver;

    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {
        $this->dispatchResolver = new PhpObjectCallDispatchResolver();
    }

    public function emit(AbstractNode $node): void
    {
        assert($node instanceof PhpObjectSetNode);

        // Same operator decision the read path makes, so `(.-slot \Foo)` and
        // `(php/:: \Foo slot)` name the same place: a class-name target is
        // static however it was written. A write is never the runtime-dispatch
        // case, which only applies to method calls.
        $isStatic = $this->dispatchResolver->resolve($node->getLeftExpr()) === PhpObjectCallDispatch::StaticClass;
        $fnCode = $isStatic ? '::' : '->';
        $targetExpr = $node->getLeftExpr()->getTargetExpr();
        $callExpr = $node->getLeftExpr()->getCallExpr();
        assert($callExpr instanceof PropertyOrConstantAccessNode);
        $propertyName = $this->propertyName($callExpr->getName()->getName(), $isStatic);
        $propertyLoc = $callExpr->getName()->getStartLocation();

        // `php/oset` is contractually required to evaluate to the *target
        // object* (not the assigned value, as a bare PHP `$o->p = v` would
        // yield). In statement context the value is discarded, so we emit the
        // assignment directly with no closure and no temp: the target is still
        // evaluated exactly once inline.
        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            $this->emitTarget($targetExpr);
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
        //
        // A class name is not a value expression, so it cannot be hoisted into
        // the temp: the assignment goes out inline and the result is the
        // class-name string, which is what a class name evaluates to in Phel.
        if ($targetExpr instanceof PhpClassNameNode) {
            new ContextualWrapEmitter($this->outputEmitter)->emit(
                $node,
                function () use ($node, $targetExpr, $fnCode, $propertyName, $propertyLoc): void {
                    $this->emitTarget($targetExpr);
                    $this->emitAssignment($node, $fnCode, $propertyName, $propertyLoc);
                },
                function () use ($targetExpr): void {
                    $this->outputEmitter->emitNode($targetExpr);
                },
            );

            return;
        }

        $targetSym = Symbol::gen('target_');

        new ContextualWrapEmitter($this->outputEmitter)->emit(
            $node,
            function () use ($node, $targetExpr, $fnCode, $propertyName, $propertyLoc, $targetSym): void {
                $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
                $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
                $this->outputEmitter->emitNode($targetExpr);
                $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());

                $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
                $this->emitAssignment($node, $fnCode, $propertyName, $propertyLoc);
            },
            function () use ($node, $targetSym): void {
                $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
            },
        );
    }

    /**
     * The member and the value, `<fnCode><property> = <right>;`, with the
     * target already emitted by the caller.
     */
    private function emitAssignment(
        PhpObjectSetNode $node,
        string $fnCode,
        string $propertyName,
        ?SourceLocation $propertyLoc,
    ): void {
        $this->outputEmitter->emitStr($fnCode, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr($propertyName, $propertyLoc);
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getRightExpr());
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
    }

    /**
     * A class name is written bare in a member access (`\Foo::$slot`), not as
     * the `\Foo::class` string its value emission produces.
     */
    private function emitTarget(AbstractNode $targetExpr): void
    {
        if ($targetExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr(
                $targetExpr->getAbsolutePhpName(),
                $targetExpr->getName()->getStartLocation(),
            );

            return;
        }

        $this->outputEmitter->emitNode($targetExpr);
    }

    /**
     * A static member reached through `::` is a class constant unless the name
     * carries the `$` sigil, and a constant is not assignable. The assignment
     * context settles the ambiguity `PropertyOrConstantAccessNode` keeps open:
     * only the property reading can be written to, so a bare name gets the
     * sigil. A name that already carries it is left alone, since doubling it
     * would emit a variable variable.
     */
    private function propertyName(string $name, bool $isStatic): string
    {
        if (!$isStatic || str_starts_with($name, '$')) {
            return $name;
        }

        return '$' . $name;
    }
}
