<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Compiler\Domain\Analyzer\Ast\AbstractNode;
use Phel\Compiler\Domain\Analyzer\Ast\LocalVarNode;
use Phel\Lang\Collections\Map\PersistentMapInterface;

use function ltrim;

/**
 * Normalisation helpers for the analyser's inferred type tags consumed by
 * the call-site specialisations. A tag is the FQN string the analyser
 * grafts onto a binding's `:tag` meta; it may carry a leading backslash,
 * so consumers compare against the normalised form.
 */
final readonly class TagNormalizer
{
    private function __construct() {}

    /**
     * Strip a leading backslash from an inferred type tag so it compares
     * equal to a PHP `::class` constant. Returns `null` for an untagged
     * binding.
     */
    public static function normalise(?string $tag): ?string
    {
        return $tag === null ? null : ltrim($tag, '\\');
    }

    public static function isPersistentMap(?string $tag): bool
    {
        return self::normalise($tag) === PersistentMapInterface::class;
    }

    /**
     * The normalised inferred type tag of a node when it is a
     * `LocalVarNode`, or `null` when the node is absent, not a local var,
     * or carries no tag. Collapses the "is this argument a tagged local?"
     * guard repeated across the call-site specialisations into one place.
     */
    public static function ofLocalVar(?AbstractNode $node): ?string
    {
        if (!$node instanceof LocalVarNode) {
            return null;
        }

        return self::normalise($node->getInferredType());
    }
}
