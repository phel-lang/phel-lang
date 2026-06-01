<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

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
}
