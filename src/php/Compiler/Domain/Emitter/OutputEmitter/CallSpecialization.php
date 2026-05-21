<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Shared\CompilerConstants;

use function count;
use function is_string;
use function ltrim;

/**
 * Syntactic predicates for `CallNode` instances the `CallEmitter`
 * specialises away from the generic dispatch path. The scanner and the
 * emitter consult the same predicates so a specialised call never gets
 * an orphan `static $__phel_call_N` declaration reserved by the cache
 * scanner.
 */
final readonly class CallSpecialization
{
    private function __construct() {}

    public static function isSpecialized(CallNode $node): bool
    {
        if (self::isStrConcat($node)) {
            return true;
        }

        if (self::isKeywordFind($node)) {
            return true;
        }

        return self::isTypedGetAccess($node);
    }

    /**
     * `(get coll k)` with two args, where the analyser has tagged the
     * target as either `PersistentVectorInterface` or
     * `PersistentMapInterface`. The runtime `phel.core/get` body walks
     * a `cond` chain covering nil, set, seq, and the generic
     * `php/aget` fallback; for a typed indexed access every branch
     * collapses to a single method call on the target collection.
     */
    public static function isTypedGetAccess(CallNode $node): bool
    {
        return self::typedGetAccessMethod($node) !== null;
    }

    /**
     * Returns the PHP method name to call on the target collection for
     * a `(get coll k)` call the emitter can specialise, or `null` when
     * the call is not a typed get-access.
     */
    public static function typedGetAccessMethod(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'get'
        ) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 2) {
            return null;
        }

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return null;
        }

        $tag = self::normalisedTag($target->getInferredType());
        return match ($tag) {
            PersistentVectorInterface::class => 'get',
            PersistentMapInterface::class => 'find',
            default => null,
        };
    }

    /**
     * `(str ...)` whose every argument compiles to a string-typed
     * expression: string literals or `LocalVarNode`s tagged `string`.
     */
    public static function isStrConcat(CallNode $node): bool
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return false;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'str'
        ) {
            return false;
        }

        $args = $node->getArguments();
        if ($args === []) {
            return false;
        }

        return array_all($args, static fn(AbstractNode $arg): bool => self::isStringConcatable($arg));
    }

    /**
     * `(:k m)` where the analyser has tagged `m` as `PersistentMapInterface`,
     * so `Keyword::__invoke` reduces to a single `$m->find($k)` call.
     */
    public static function isKeywordFind(CallNode $node): bool
    {
        $fn = $node->getFn();
        if (!$fn instanceof LiteralNode || !$fn->getValue() instanceof Keyword) {
            return false;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return false;
        }

        $arg = $args[0];
        return $arg instanceof LocalVarNode
            && self::isPersistentMapTag($arg->getInferredType());
    }

    private static function isStringConcatable(AbstractNode $arg): bool
    {
        if ($arg instanceof LiteralNode && is_string($arg->getValue())) {
            return true;
        }

        if (!$arg instanceof LocalVarNode) {
            return false;
        }

        $tag = $arg->getInferredType();
        return $tag !== null && ltrim($tag, '\\') === 'string';
    }

    private static function isPersistentMapTag(?string $tag): bool
    {
        return self::normalisedTag($tag) === PersistentMapInterface::class;
    }

    private static function normalisedTag(?string $tag): ?string
    {
        return $tag === null ? null : ltrim($tag, '\\');
    }
}
