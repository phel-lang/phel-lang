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

        // `php/oset` is contractually required to evaluate to the *target
        // object* (not the assigned value, as a bare PHP `$o->p = v` would
        // yield). In statement context that value is discarded, so the
        // assignment goes out with no closure and no temp: the target is still
        // evaluated exactly once, inline.
        if ($node->getEnv()->isContext(NodeEnvironment::CONTEXT_STATEMENT)) {
            $this->emitAssignment($node, null);
            $this->outputEmitter->emitStr(';', $node->getStartSourceLocation());

            return;
        }

        // Expression or return context: the value is consumed, so a computed
        // target is hoisted into a temp that the form can both assign through
        // and yield, preserving the "returns the target" semantics. A class
        // name is not a computed expression, so it needs no temp and yields
        // itself, which in Phel is the class-name string.
        //
        // The shared kernel wraps this in an IIFE only in expression context;
        // in return context it elides the closure and emits plain statements.
        $targetSym = $this->needsTemp($node) ? Symbol::gen('target_') : null;

        new ContextualWrapEmitter($this->outputEmitter)->emit(
            $node,
            function () use ($node, $targetSym): void {
                if ($targetSym instanceof Symbol) {
                    $this->emitTempAssignment($node, $targetSym);
                }

                $this->emitAssignment($node, $targetSym);
                $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
            },
            function () use ($node, $targetSym): void {
                $this->emitResult($node, $targetSym);
            },
        );
    }

    /**
     * `<target><::|-><property> = <value>`, without its terminator. The target
     * is the temp when there is one, and the place written in the source when
     * there is not.
     */
    private function emitAssignment(PhpObjectSetNode $node, ?Symbol $targetSym): void
    {
        $isStatic = $this->isStatic($node);
        $callExpr = $node->getLeftExpr()->getCallExpr();
        assert($callExpr instanceof PropertyOrConstantAccessNode);

        if ($targetSym instanceof Symbol) {
            $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
        } else {
            $this->emitTarget($node);
        }

        $this->outputEmitter->emitStr($isStatic ? '::' : '->', $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(
            $this->propertyName($callExpr->getName()->getName(), $isStatic),
            $callExpr->getName()->getStartLocation(),
        );
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getRightExpr());
    }

    private function emitTempAssignment(PhpObjectSetNode $node, Symbol $targetSym): void
    {
        $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());
        $this->outputEmitter->emitStr(' = ', $node->getStartSourceLocation());
        $this->outputEmitter->emitNode($node->getLeftExpr()->getTargetExpr());
        $this->outputEmitter->emitLine(';', $node->getStartSourceLocation());
    }

    /**
     * The value the form evaluates to: the temp, or the class name as the
     * `\Foo::class` string a class name evaluates to everywhere else.
     */
    private function emitResult(PhpObjectSetNode $node, ?Symbol $targetSym): void
    {
        if ($targetSym instanceof Symbol) {
            $this->outputEmitter->emitPhpVariable($targetSym, $node->getStartSourceLocation());

            return;
        }

        $this->outputEmitter->emitNode($node->getLeftExpr()->getTargetExpr());
    }

    /**
     * A class name is written bare on the left of a member access
     * (`\Foo::$slot`), not as the `\Foo::class` string its value emission
     * produces.
     */
    private function emitTarget(PhpObjectSetNode $node): void
    {
        $targetExpr = $node->getLeftExpr()->getTargetExpr();

        if ($targetExpr instanceof PhpClassNameNode) {
            $this->outputEmitter->emitStr(
                $targetExpr->getAbsolutePhpName(),
                $targetExpr->getName()->getStartLocation(),
            );

            return;
        }

        $this->outputEmitter->emitNode($targetExpr);
    }

    private function needsTemp(PhpObjectSetNode $node): bool
    {
        return !$node->getLeftExpr()->getTargetExpr() instanceof PhpClassNameNode;
    }

    /**
     * The same operator decision the read path makes, so `(.-slot \Foo)` and
     * `(php/:: \Foo slot)` name one place: a class-name target is static
     * however it was written. A write is never the runtime-dispatch case,
     * which only applies to method calls.
     */
    private function isStatic(PhpObjectSetNode $node): bool
    {
        return $this->dispatchResolver->resolve($node->getLeftExpr()) === PhpObjectCallDispatch::StaticClass;
    }

    /**
     * A static member reached through `::` is a class constant unless the name
     * carries the `$` sigil, and a constant is not assignable. The assignment
     * settles the ambiguity `PropertyOrConstantAccessNode` keeps open: only the
     * property reading can be written to, so a bare name gets the sigil. A name
     * that already carries it is left alone, since doubling it would emit a
     * variable variable.
     */
    private function propertyName(string $name, bool $isStatic): string
    {
        if (!$isStatic || str_starts_with($name, '$')) {
            return $name;
        }

        return '$' . $name;
    }
}
