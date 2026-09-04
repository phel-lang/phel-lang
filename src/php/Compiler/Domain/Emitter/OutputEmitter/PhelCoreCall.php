<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Shared\CompilerConstants;
use WeakMap;

/**
 * Shared shape check for a `CallNode` whose target resolves to a
 * `phel.core` global function. Every emitter specialisation begins by
 * asking "is this a call to phel.core/<name>?"; this collapses the
 * repeated GlobalVarNode + namespace (+ name) guard into one place.
 *
 * @internal
 */
final class PhelCoreCall
{
    /**
     * Answers already given, keyed by the node that asked. `CallEmitter`
     * probes eleven specialisation families in turn and each one opens with
     * this question about the same node, so the second probe onwards is a
     * lookup. A `WeakMap` rather than an id-keyed array: object ids are
     * reused after collection, and an AST node is short-lived.
     *
     * `false` stands for "asked, and it is not a phel.core call", which `null`
     * could not, since that is also the answer being cached.
     *
     * @var WeakMap<CallNode, false|string>|null
     */
    private static ?WeakMap $answers = null;

    private function __construct() {}

    /**
     * The bare `phel.core` function name this call resolves to, or null
     * when the call target is not a `phel.core` global var.
     */
    public static function nameOf(CallNode $node): ?string
    {
        $answers = self::answers();
        $cached = $answers[$node] ?? null;
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        $name = self::resolveNameOf($node);
        $answers[$node] = $name ?? false;

        return $name;
    }

    /**
     * Whether this call resolves to `phel.core/<fnName>`.
     */
    public static function is(CallNode $node, string $fnName): bool
    {
        return self::nameOf($node) === $fnName;
    }

    /**
     * @return WeakMap<CallNode, false|string>
     */
    private static function answers(): WeakMap
    {
        if (self::$answers instanceof WeakMap) {
            return self::$answers;
        }

        /** @var WeakMap<CallNode, false|string> $answers */
        $answers = new WeakMap();

        return self::$answers = $answers;
    }

    private static function resolveNameOf(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        return $fn->getName()->getName();
    }
}
