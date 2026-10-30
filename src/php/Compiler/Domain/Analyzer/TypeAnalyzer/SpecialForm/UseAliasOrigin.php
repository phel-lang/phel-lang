<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

/**
 * @internal
 */
final class UseAliasOrigin
{
    private const string DEFAULT_ALIAS = 'phel.compiler/default-use-alias';

    public static function isDefault(Symbol $target): bool
    {
        return $target->getMeta()?->find(Keyword::create(self::DEFAULT_ALIAS)) === true;
    }

    public static function markDefault(Symbol $target): Symbol
    {
        return $target->withMeta(($target->getMeta() ?? Phel::map())->put(Keyword::create(self::DEFAULT_ALIAS), true));
    }

    public static function explicit(Symbol $target): Symbol
    {
        $meta = $target->getMeta();
        $marker = Keyword::create(self::DEFAULT_ALIAS);
        if (!$meta instanceof PersistentMapInterface || !$meta->contains($marker)) {
            return $target;
        }

        return $target->withMeta($meta->remove($marker));
    }
}
