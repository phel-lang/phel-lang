<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\SymbolNode;

/**
 * Reads the head symbol of the form a zipper location sits in and compares it
 * against an indenter's configured symbol.
 */
trait FormSymbolMatcherTrait
{
    private function indentMatches(string $key, ?Symbol $formSymbol): bool
    {
        return $formSymbol instanceof Symbol && $key === $formSymbol->getName();
    }

    private function formSymbol(ParseTreeZipper $loc): ?Symbol
    {
        $leftMostNode = $loc->leftMost()->getNode();

        if ($leftMostNode instanceof SymbolNode) {
            return $leftMostNode->getValue();
        }

        return null;
    }
}
