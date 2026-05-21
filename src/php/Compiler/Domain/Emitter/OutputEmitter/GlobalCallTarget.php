<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Lang\Keyword;

use function str_starts_with;

/**
 * Predicates shared by the call-site cache scanner and the `CallEmitter`
 * to keep both sides aligned on which `CallNode` instances are eligible
 * for the per-fn `static $__phel_call_N` slot and for emission via the
 * non-magic `AbstractFn::call(...)` dispatch.
 */
final readonly class GlobalCallTarget
{
    private function __construct() {}

    public static function isGlobalFnCall(CallNode $node): bool
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return false;
        }

        if (self::isSelfCall($fn, $node)) {
            return false;
        }

        return self::hasFnMeta($fn);
    }

    /**
     * Matches {@see CallEmitter::isSelfCall()}: a global resolves to `$this`
     * when its `<ns>\<name>` matches the current `boundTo`, including any
     * `let`/`loop` suffix appended while analysing the body.
     */
    public static function isSelfCall(GlobalVarNode $fn, CallNode $node): bool
    {
        $boundTo = $node->getEnv()->getBoundTo();
        if ($boundTo === '') {
            return false;
        }

        $expected = $fn->getNamespace() . '\\' . $fn->getName()->getName();
        return $boundTo === $expected
            || str_starts_with($boundTo, $expected . '.');
    }

    /**
     * `defn` / `defmacro` always set `arglists` (and `min-arity`); plain
     * `(def x value)` does not. Using the meta map keeps the check
     * independent of the runtime registry being populated.
     */
    private static function hasFnMeta(GlobalVarNode $fn): bool
    {
        $meta = $fn->getMeta();
        if ($meta->find('arglists') !== null) {
            return true;
        }

        return $meta->find(Keyword::create('arglists')) !== null;
    }
}
