<?php

declare(strict_types=1);

namespace Phel\Lang\TagHandlers;

use Phel\Lang\TagRegistry;

/**
 * Registers Phel's built-in tagged-literal handlers with the global
 * `TagRegistry`. Safe to call multiple times — registration is
 * idempotent and last registration wins.
 */
final class BuiltinTagHandlers
{
    /**
     * The identifiers of tags that resolve to node-structure-aware
     * handlers implemented directly in the reader instead of a
     * registry callable (e.g. `#php` which dispatches on the token type
     * of its following form).
     *
     * @var list<string>
     */
    public const array RESERVED = ['php'];

    public static function registerAll(TagRegistry $registry): void
    {
        $registry->register('inst', new InstTagHandler());
        $registry->register('regex', new RegexTagHandler());
        $registry->register('uuid', new UuidTagHandler());
    }
}
