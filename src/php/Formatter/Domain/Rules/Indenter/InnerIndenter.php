<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Lang\Symbol;
use Phel\Shared\Parser\Node\SymbolNode;

/**
 * Indents a form's body by one extra level relative to its head line.
 *
 * Matches either the current location's head symbol or the head symbol of the
 * ancestor reached by walking {@see self::$depth} levels up, allowing nested
 * constructs to be matched at a fixed ancestry distance.
 */
final readonly class InnerIndenter implements IndenterInterface
{
    private LineIndenter $lineIndenter;

    /**
     * @param string $symbol The Phel form symbol to match (e.g. `defn`, `fn`)
     * @param int    $depth  Number of ancestor levels to climb (skipping
     *                       whitespace) before checking the indent match
     */
    public function __construct(
        private string $symbol,
        private int $depth,
    ) {
        $this->lineIndenter = new LineIndenter();
    }

    public function getMargin(ParseTreeZipper $loc, int $indentWidth): ?int
    {
        $top = $loc;
        for ($i = 0; $i < $this->depth; ++$i) {
            $top = $top->upSkipWhitespace();
        }

        if ($this->indentMatches($this->symbol, $this->formSymbol($loc))
            || $this->indentMatches($this->symbol, $this->formSymbol($top))) {
            $up = $loc->upSkipWhitespace();

            return $this->lineIndenter->getMargin($up, $indentWidth) + $indentWidth;
        }

        return null;
    }

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
