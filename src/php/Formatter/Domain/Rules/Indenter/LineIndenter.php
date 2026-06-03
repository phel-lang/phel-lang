<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Shared\Parser\Node\InnerNodeInterface;

use function strlen;

/**
 * Computes the left margin (indentation width) of a location by reconstructing
 * the text of the line that precedes it and measuring that line's length.
 */
final class LineIndenter implements IndenterInterface
{
    public function getMargin(ParseTreeZipper $loc, int $indentWidth): int
    {
        return strlen($this->lastLineInString($this->priorLineString($loc)));
    }

    /**
     * Walks backwards (left, then up) from the given location, prepending each
     * node's code, until a node containing a newline is reached or the root is
     * hit. The returned string holds the source from the start of the current
     * line up to the location.
     */
    private function priorLineString(ParseTreeZipper $form): string
    {
        $loc = $form;
        $str = '';
        while (!$loc->isFirst() || !$loc->isTop()) {
            if (!$loc->isFirst()) {
                $loc = $loc->left();
                $s = $loc->getNode()->getCode();
                $str = $s . $str;

                if (str_contains($s, "\n")) {
                    return $str;
                }
            } else {
                $loc = $loc->up();
                /** @var InnerNodeInterface $node */
                $node = $loc->getNode();
                $str = $node->getCodePrefix() . $str;
            }
        }

        return $str;
    }

    /**
     * Returns the substring after the last newline (the final line), or the
     * whole string when it contains no newline.
     */
    private function lastLineInString(string $s): string
    {
        $pos = strrpos($s, "\n");
        if ($pos !== false) {
            return substr($s, $pos + 1);
        }

        return $s;
    }
}
