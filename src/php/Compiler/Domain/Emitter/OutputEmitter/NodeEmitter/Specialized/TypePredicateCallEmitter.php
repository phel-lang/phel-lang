<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\TypePredicateSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function sprintf;

/**
 * Specialisations gated by {@see TypePredicateSpecialization}: the numeric
 * sign predicates `(zero? x)` / `(pos? x)` / `(neg? x)` and the type
 * predicates (`int?`, `map?`, `vector?`, `seq?`, ...), each inlined to a
 * native comparison or `instanceof` fragment.
 */
final readonly class TypePredicateCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        if ($this->tryEmitNumericPredicate($node)) {
            return true;
        }

        return $this->tryEmitTypePredicate($node);
    }

    /**
     * `(zero? x)` / `(pos? x)` / `(neg? x)` on an `int` / `float`
     * tagged local — emit the native comparison directly.
     */
    private function tryEmitNumericPredicate(CallNode $node): bool
    {
        $name = TypePredicateSpecialization::isNumericPredicate($node);
        if ($name === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);

        $op = match ($name) {
            'zero?' => ' === 0',
            'pos?' => ' > 0',
            'neg?' => ' < 0',
            default => ' === 0',
        };

        $this->outputEmitter->emitStr($op . ')', $loc);
        return true;
    }

    /**
     * Splice the argument into the native predicate fragment from
     * `TypePredicateSpecialization::typePredicateFragment`. The fragment uses
     * `sprintf` placeholders so multi-instanceof predicates (`map?`,
     * `vector?`, `seq?`) can reference the argument multiple times.
     */
    private function tryEmitTypePredicate(CallNode $node): bool
    {
        $fragment = TypePredicateSpecialization::typePredicateFragment($node);
        if ($fragment === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();

        $argEmit = $this->outputEmitter->captureNodeAsExpression($node->getArguments()[0]);

        $this->outputEmitter->emitStr(sprintf($fragment, $argEmit), $loc);
        return true;
    }
}
