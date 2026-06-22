<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Shared\CompilerConstants;

/**
 * Shared shape check for a `CallNode` whose target resolves to a
 * `phel.core` global function. Every emitter specialisation begins by
 * asking "is this a call to phel.core/<name>?"; this collapses the
 * repeated GlobalVarNode + namespace (+ name) guard into one place.
 */
final readonly class PhelCoreCall
{
    private function __construct() {}

    /**
     * The bare `phel.core` function name this call resolves to, or null
     * when the call target is not a `phel.core` global var.
     */
    public static function nameOf(CallNode $node): ?string
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

    /**
     * Whether this call resolves to `phel.core/<fnName>`.
     */
    public static function is(CallNode $node, string $fnName): bool
    {
        return self::nameOf($node) === $fnName;
    }
}
