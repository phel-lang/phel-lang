<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Shared\CompilerConstants;

use function count;

/**
 * Call-site eligibility for the `phel.core` atom accessors (`deref`,
 * `reset!`) that {@see NodeEmitter\CallEmitter}
 * lowers to a direct `php/->` method call instead of a registry dispatch.
 */
final readonly class AtomMethodSpecialization
{
    private function __construct() {}

    /**
     * `(deref x)` 1-arg / `(reset! v val)` 2-arg — runtime bodies are
     * single `php/->` method calls. Direct emission saves the registry
     * lookup + adapter frame. The 3-arg deref overload (timeout) keeps
     * the runtime dispatch because its body is a cond chain. Returns
     * the (method, arg-indices-after-target) tuple, or `null`.
     *
     * @return array{0: string, 1: list<int>}|null
     */
    public static function atomMethodCall(CallNode $node): ?array
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
        ) {
            return null;
        }

        $name = $fn->getName()->getName();
        $argc = count($node->getArguments());

        if ($name === 'deref' && $argc === 1) {
            return ['deref', []];
        }

        if ($name === 'reset!' && $argc === 2) {
            return ['set', [1]];
        }

        return null;
    }

    public static function isAtomMethodCall(CallNode $node): bool
    {
        return self::atomMethodCall($node) !== null;
    }
}
