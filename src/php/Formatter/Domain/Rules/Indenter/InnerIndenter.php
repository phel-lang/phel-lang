<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Compiler\Parser\ParserNode\SymbolNode;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Lang\Symbol;

final class InnerIndenter implements IndenterInterface
{
    private int $depth;
    private string $symbol;
    private LineIndenter $lineIndenter;

    public function __construct(string $symbol, int $depth)
    {
        $this->depth = $depth;
        $this->symbol = $symbol;
        $this->lineIndenter = new LineIndenter();
    }

    public function getMargin(ParseTreeZipper $loc, int $indentWidth): ?int
    {
        $top = $loc;
        for ($i = 0; $i < $this->depth; $i++) {
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
        return $formSymbol && $key === $formSymbol->getName();
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
