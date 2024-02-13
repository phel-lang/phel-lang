<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Lang\Symbol;

final readonly class InnerIndenter implements IndenterInterface
{
    private LineIndenter $lineIndenter;

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

            return (int) $this->lineIndenter->getMargin($up, $indentWidth) + $indentWidth;
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
