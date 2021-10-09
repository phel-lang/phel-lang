<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Compiler\Parser\ParserNode\SymbolNode;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Lang\Symbol;

final class BlockIndenter implements IndenterInterface
{
    private int $index;
    private string $symbol;
    private ListIndenter $listIndenter;

    public function __construct(string $symbol, int $index)
    {
        $this->index = $index;
        $this->symbol = $symbol;
        $this->listIndenter = new ListIndenter();
    }

    public function getMargin(ParseTreeZipper $loc, int $indentWidth): ?int
    {
        if ($this->indentMatches($this->symbol, $this->formSymbol($loc))) {
            $locAfterIndex = $this->nthForm($loc, $this->index + 1);

            if (is_null($locAfterIndex) || $this->firstFormInLine($locAfterIndex)) {
                return (new InnerIndenter($this->symbol, 0))->getMargin($loc, $indentWidth);
            }
            return $this->listIndenter->getMargin($loc, $indentWidth);
        }

        return null;
    }

    private function nthForm(ParseTreeZipper $loc, int $n): ?ParseTreeZipper
    {
        try {
            $x = $loc->leftMostSkipWhitespace();
            for ($i = 0; $i < $n; $i++) {
                $x = $x->rightSkipWhitespace();
            }

            return $x;
        } catch (ZipperException $e) {
            return null;
        }
    }

    private function firstFormInLine(ParseTreeZipper $loc): bool
    {
        if ($loc->isFirst()) {
            return true;
        }

        /** @var ParseTreeZipper $left */
        $left = $loc->left();
        if ($left->isWhitespace()) {
            return $this->firstFormInLine($left);
        }

        return $left->isLineBreak() || $left->isComment();
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
