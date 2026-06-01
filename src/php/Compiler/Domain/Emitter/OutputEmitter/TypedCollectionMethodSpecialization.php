<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\CallNode;
use Phel\Compiler\Domain\Analyzer\Ast\GlobalVarNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\SeqInterface;
use Phel\Shared\CompilerConstants;

use function count;

/**
 * Call-site eligibility for `phel.core` accessors on a `LocalVarNode`
 * whose inferred tag is a persistent collection type, which
 * {@see NodeEmitter\CallEmitter}
 * lowers to a direct method call instead of the runtime `cond`-chain body.
 */
final readonly class TypedCollectionMethodSpecialization
{
    /** @var array<string, string> Phel core seq accessor → PHP method */
    private const array SEQ_METHODS = [
        'first' => 'first',
        'rest' => 'rest',
    ];

    /** @var array<string, true> Tags whose runtime types implement SeqInterface */
    private const array SEQ_TAGS = [
        SeqInterface::class => true,
        PersistentVectorInterface::class => true,
        PersistentListInterface::class => true,
    ];

    private function __construct() {}

    /**
     * `(nth v i)` / `(count v)` where the analyser has tagged the
     * target as `PersistentVectorInterface`. The runtime `nth` body
     * walks a `cond` over set / seq / vector / map / php-array; for a
     * typed vector every branch collapses to a single method call.
     *
     * @return array{method: string, args: list<int>}|null list of arg
     *                                                     indices to
     *                                                     pass as
     *                                                     method args
     */
    public static function typedVectorMethodCall(CallNode $node): ?array
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $args = $node->getArguments();
        $target = $args[0] ?? null;
        if (!$target instanceof LocalVarNode) {
            return null;
        }

        if (TagNormalizer::normalise($target->getInferredType()) !== PersistentVectorInterface::class) {
            return null;
        }

        $name = $fn->getName()->getName();

        if ($name === 'count' && count($args) === 1) {
            return ['method' => 'count', 'args' => []];
        }

        if ($name === 'nth' && count($args) === 2) {
            return ['method' => 'get', 'args' => [1]];
        }

        return null;
    }

    public static function isTypedVectorAccessor(CallNode $node): bool
    {
        return self::typedVectorMethodCall($node) !== null;
    }

    /**
     * `(first s)` / `(rest s)` where the analyser has tagged the
     * target as a `SeqInterface` / `PersistentVectorInterface` /
     * `PersistentListInterface`. The runtime body of each fn walks a
     * cond chain that handles nil, strings, php-arrays, sets, etc.;
     * for a tagged seq every branch collapses to `$s->first()` /
     * `$s->rest()`.
     */
    public static function typedSeqMethodName(CallNode $node): ?string
    {
        $fn = $node->getFn();
        if (!$fn instanceof GlobalVarNode) {
            return null;
        }

        if ($fn->getNamespace() !== CompilerConstants::PHEL_CORE_NAMESPACE) {
            return null;
        }

        $name = $fn->getName()->getName();
        if (!isset(self::SEQ_METHODS[$name])) {
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
        if ($tag === null || !isset(self::SEQ_TAGS[$tag])) {
            return null;
        }

        return self::SEQ_METHODS[$name];
    }

    public static function isTypedSeqAccessor(CallNode $node): bool
    {
        return self::typedSeqMethodName($node) !== null;
    }
}
