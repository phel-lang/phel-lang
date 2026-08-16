<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use Phel\Shared\Parser\Node\InnerNodeInterface;
use Phel\Shared\Parser\Node\NodeInterface;

/**
 * Renders "before -> after" for a parent form and one alternative child
 * list, without touching the tree: the alternative is emitted through the
 * parent's own delimiters.
 *
 * @internal
 */
final class Description
{
    /**
     * @param list<NodeInterface> $children
     */
    public static function ofChildren(InnerNodeInterface $parent, array $children): string
    {
        return $parent->getCode() . ' -> ' . self::codeOf($parent, $children);
    }

    /**
     * @param list<NodeInterface> $children
     */
    public static function codeOf(InnerNodeInterface $parent, array $children): string
    {
        $code = '';
        foreach ($children as $child) {
            $code .= $child->getCode();
        }

        return $parent->getCodePrefix() . $code . ($parent->getCodePostfix() ?? '');
    }
}
