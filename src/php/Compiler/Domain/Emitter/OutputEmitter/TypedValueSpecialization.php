<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LiteralNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\Collections\HashSet\PersistentHashSetInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\ContainsInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Shared\CompilerConstants;

use function count;
use function in_array;

/**
 * Call-site eligibility for `phel.core` operations on a single
 * tagged-value target — `contains?`, `empty?`, `name` / `namespace`,
 * and keyword-find (`(:k m)`) — which
 * {@see NodeEmitter\CallEmitter}
 * lowers to a native check / accessor instead of a registry dispatch.
 */
final readonly class TypedValueSpecialization
{
    private function __construct() {}

    /**
     * `(contains? coll k)` on a target tagged as a
     * `\Phel\Lang\ContainsInterface`-implementing collection
     * (PersistentMap / PersistentVector / PersistentHashSet) or as a
     * PHP `array`. The runtime body walks
     * `nil? → ContainsInterface → is_array → is_string → throw`; the
     * tagged target collapses to one of the first two branches.
     *
     * Returns:
     *  - `'method'` for ContainsInterface targets — emit `$coll->contains($k)`
     *  - `'array'` for array tags                 — emit `array_key_exists($k, $coll)`
     *  - `null` otherwise.
     */
    public static function containsCheckKind(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'contains?'
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

        $tag = TagNormalizer::normalise($target->getInferredType());
        if ($tag === null) {
            return null;
        }

        if ($tag === 'array') {
            return 'array';
        }

        $containsInterfaces = [
            PersistentMapInterface::class,
            PersistentVectorInterface::class,
            PersistentHashSetInterface::class,
            ContainsInterface::class,
        ];

        return in_array($tag, $containsInterfaces, true) ? 'method' : null;
    }

    public static function isContainsCheck(CallNode $node): bool
    {
        return self::containsCheckKind($node) !== null;
    }

    /**
     * `(empty? x)` on a tagged local. Returns the PHP expression
     * fragment with `%s` substitution for the (already-emitted)
     * argument, or `null` when the call is not eligible.
     *
     *  - `^array x`                       → `(%s === [])`
     *  - `^string x`                      → `(%s === '')`
     *  - `^int x`                         → `(%s === 0)`
     *  - `^PersistentMapInterface x`      → `(%s->count() === 0)`
     *  - `^PersistentVectorInterface x`   → `(%s->count() === 0)`
     */
    public static function emptyCheckFragment(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
            || $fn->getName()->getName() !== 'empty?'
        ) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return null;
        }

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return null;
        }

        $tag = TagNormalizer::normalise($target->getInferredType());
        if ($tag === null) {
            return null;
        }

        return match ($tag) {
            'array' => '(%s === [])',
            'string' => "(%s === '')",
            'int' => '(%s === 0)',
            PersistentMapInterface::class,
            PersistentVectorInterface::class => '(%s->count() === 0)',
            default => null,
        };
    }

    public static function isEmptyCheck(CallNode $node): bool
    {
        return self::emptyCheckFragment($node) !== null;
    }

    /**
     * `(name x)` / `(namespace x)` on a target tagged
     * `\Phel\Lang\Keyword` or `\Phel\Lang\Symbol`. The runtime body
     * for `name` is `(if (string? x) x (php/-> x (getName)))`; the
     * tagged target always hits the second branch. Returns the
     * method name (`getName` / `getNamespace`) when eligible, `null`
     * otherwise.
     */
    public static function namedAccessorMethod(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode
            || $fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE
        ) {
            return null;
        }

        $args = $node->getArguments();
        if (count($args) !== 1) {
            return null;
        }

        $target = $args[0];
        if (!$target instanceof LocalVarNode) {
            return null;
        }

        $tag = TagNormalizer::normalise($target->getInferredType());
        if ($tag !== Keyword::class && $tag !== Symbol::class) {
            return null;
        }

        return match ($fn->getName()->getName()) {
            'name' => 'getName',
            'namespace' => 'getNamespace',
            default => null,
        };
    }

    public static function isNamedAccessor(CallNode $node): bool
    {
        return self::namedAccessorMethod($node) !== null;
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
            && TagNormalizer::isPersistentMap($arg->getInferredType());
    }
}
