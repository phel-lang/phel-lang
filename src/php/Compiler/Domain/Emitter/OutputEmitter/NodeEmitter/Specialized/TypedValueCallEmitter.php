<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\TypedValueSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function assert;
use function count;
use function explode;

/**
 * Specialisations gated by {@see TypedValueSpecialization}: keyword lookups,
 * `(name x)` / `(namespace x)` accessors, `(empty? x)`, and `(contains? c k)`
 * over targets whose analyser tag pins the access to a single native form.
 */
final readonly class TypedValueCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        if ($this->tryEmitKeywordFind($node)) {
            return true;
        }

        if ($this->tryEmitNamedAccessor($node)) {
            return true;
        }

        if ($this->tryEmitEmptyCheck($node)) {
            return true;
        }

        return $this->tryEmitContainsCheck($node);
    }

    /**
     * Specialise `(:k m)` to `$m->find(\Phel::keyword("k"))` when the
     * analyser has proved `m` to be a `PersistentMapInterface`. The
     * runtime `Keyword::__invoke` dispatches on the target's runtime
     * type to pick between `ArrayAccess`, `ContainsInterface`, and the
     * `nil` fallback; a typed map collapses that dispatch to the single
     * `find` call the map already exposes, returning `null` on miss to
     * match the 1-arg keyword-accessor contract.
     */
    private function tryEmitKeywordFind(CallNode $node): bool
    {
        if (!TypedValueSpecialization::isKeywordFind($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr('->find(', $loc);
        $this->outputEmitter->emitNode($node->getFn());
        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }

    /**
     * `(name x)` / `(namespace x)` on a target tagged
     * `\Phel\Lang\Keyword` or `\Phel\Lang\Symbol` — emit the direct
     * method call, skipping the runtime cond chain.
     */
    private function tryEmitNamedAccessor(CallNode $node): bool
    {
        $method = TypedValueSpecialization::namedAccessorMethod($node);
        if ($method === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr('->' . $method . '())', $loc);
        return true;
    }

    /**
     * `(empty? x)` on a tagged local — emit the native check
     * specific to the tag, skipping the runtime cond chain.
     */
    private function tryEmitEmptyCheck(CallNode $node): bool
    {
        $fragment = TypedValueSpecialization::emptyCheckFragment($node);
        if ($fragment === null) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $parts = explode('%s', $fragment, 2);
        assert(count($parts) === 2);

        $this->outputEmitter->emitStr($parts[0], $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr($parts[1], $loc);
        return true;
    }

    /**
     * `(contains? coll k)` on a tagged target — emit the direct
     * method or `array_key_exists` form.
     */
    private function tryEmitContainsCheck(CallNode $node): bool
    {
        $kind = TypedValueSpecialization::containsCheckKind($node);
        if ($kind === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();

        if ($kind === 'array') {
            $this->outputEmitter->emitStr('array_key_exists(', $loc);
            $this->outputEmitter->emitNode($args[1]);
            $this->outputEmitter->emitStr(', ', $loc);
            $this->outputEmitter->emitNode($args[0]);
            $this->outputEmitter->emitStr(')', $loc);
            return true;
        }

        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->contains(', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }
}
