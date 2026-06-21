<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter\NodeEmitter\Specialized;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Emitter\OutputEmitter\CallSpecialization;
use Phel\Compiler\Domain\Emitter\OutputEmitterInterface;

use function count;

/**
 * Specialisations gated by {@see CallSpecialization}: `(str ...)` over
 * string-typed args, `(get coll k)` on a tagged persistent collection, and
 * native PHP-array `get` / `count`. Each collapses a `phel.core` cond chain
 * to the single native form the analyser tag has already proven safe.
 */
final readonly class CoreFnCallEmitter implements SpecializedCallEmitterInterface
{
    public function __construct(
        private OutputEmitterInterface $outputEmitter,
    ) {}

    public function tryEmit(CallNode $node): bool
    {
        if ($this->tryEmitStrConcat($node)) {
            return true;
        }

        if ($this->tryEmitTypedGetAccess($node)) {
            return true;
        }

        if ($this->tryEmitTypedPhpArrayGet($node)) {
            return true;
        }

        if ($this->tryEmitTypedPhpArrayCount($node)) {
            return true;
        }

        if ($this->tryEmitTypedStringCount($node)) {
            return true;
        }

        return $this->tryEmitTypedStringFirst($node);
    }

    /**
     * Specialise `(str ...)` to PHP `.` concatenation when every arg
     * compiles to a string-typed expression. The runtime `phel.core/str`
     * does a per-arg `val-to-str` dispatch plus a `StringBuilder`-style
     * accumulator pass; when every arg is already a string the result is
     * the same plain `.` chain, so we emit it directly and skip both the
     * registry lookup and the runtime walk.
     *
     * Eligibility lives on {@see CallSpecialization::isStrConcat()} so the
     * cache scanner can skip reserving a `static $__phel_call_N` slot for
     * the call we are about to specialise.
     */
    private function tryEmitStrConcat(CallNode $node): bool
    {
        if (!CallSpecialization::isStrConcat($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        foreach ($node->getArguments() as $i => $arg) {
            if ($i > 0) {
                $this->outputEmitter->emitStr(' . ', $loc);
            }

            $this->outputEmitter->emitNode($arg);
        }

        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Specialise `(get coll k)` to a direct method call when the target
     * carries a `PersistentVectorInterface` or `PersistentMapInterface`
     * tag. Skips the cond chain in `phel.core/get`'s body (nil / set /
     * seq / php-aget fallback) for the hot indexed-access shape.
     *
     * Two-arg form only: the three-arg `(get coll k default)` shape
     * needs an explicit `contains?` probe to honour the default, so
     * the cond chain is still the right path.
     */
    private function tryEmitTypedGetAccess(CallNode $node): bool
    {
        $method = CallSpecialization::typedGetAccessMethod($node);
        if ($method === null) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('->' . $method . '(', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr('))', $loc);
        return true;
    }

    /**
     * Specialise `(get arr k)` / `(get arr k default)` on a target
     * tagged `array` to a native PHP subscript with the null-coalescing
     * fallback. Matches the runtime `get` semantics for PHP arrays —
     * `(php/aget ds k)` then "if nil return default" — because PHP's
     * `??` treats both absent keys and explicit nulls as triggering
     * the fallback.
     */
    private function tryEmitTypedPhpArrayGet(CallNode $node): bool
    {
        if (!CallSpecialization::isTypedPhpArrayGet($node)) {
            return false;
        }

        $args = $node->getArguments();
        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($args[0]);
        $this->outputEmitter->emitStr('[', $loc);
        $this->outputEmitter->emitNode($args[1]);
        $this->outputEmitter->emitStr('] ?? ', $loc);
        if (count($args) === 3) {
            $this->outputEmitter->emitNode($args[2]);
        } else {
            $this->outputEmitter->emitStr('null', $loc);
        }

        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Specialise `(count arr)` on a target tagged `array` to a native
     * `count($arr)` call. The runtime body would walk a cond chain
     * over the standard collection shapes before reaching the same
     * `php/count` branch.
     */
    private function tryEmitTypedPhpArrayCount(CallNode $node): bool
    {
        if (!CallSpecialization::isTypedPhpArrayCount($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('count(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Specialise `(count s)` on a target tagged `string` to a native
     * `mb_strlen($s)` call. The runtime `count` body reaches
     * `(php/mb_strlen coll)` for the string branch, so the multibyte
     * codepoint count is byte-for-byte identical.
     */
    private function tryEmitTypedStringCount(CallNode $node): bool
    {
        if (!CallSpecialization::isTypedStringCount($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $this->outputEmitter->emitStr('mb_strlen(', $loc);
        $this->outputEmitter->emitNode($node->getArguments()[0]);
        $this->outputEmitter->emitStr(')', $loc);
        return true;
    }

    /**
     * Specialise `(first s)` on a target tagged `string` to the native
     * multibyte first-char slice with the empty-string nil guard. The
     * runtime `first` reaches `first-of-string`, i.e.
     * `(if (php/=== "" s) nil (php/mb_substr s 0 1))`; the emitted
     * `($s === '' ? null : mb_substr($s, 0, 1))` preserves both the nil
     * contract and the multibyte slice.
     */
    private function tryEmitTypedStringFirst(CallNode $node): bool
    {
        if (!CallSpecialization::isTypedStringFirst($node)) {
            return false;
        }

        $loc = $node->getStartSourceLocation();
        $arg = $node->getArguments()[0];

        $this->outputEmitter->emitStr('(', $loc);
        $this->outputEmitter->emitNode($arg);
        $this->outputEmitter->emitStr(" === '' ? null : mb_substr(", $loc);
        $this->outputEmitter->emitNode($arg);
        $this->outputEmitter->emitStr(', 0, 1))', $loc);
        return true;
    }
}
