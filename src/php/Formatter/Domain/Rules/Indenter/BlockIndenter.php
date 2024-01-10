<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;
use Phel\Formatter\Domain\Rules\Zipper\ZipperException;
use Phel\Lang\Symbol;

use function is_null;

final readonly class BlockIndenter implements IndenterInterface
{
    private ListIndenter $listIndenter;

    public function __construct(
        private string $symbol,
        private int $index,
    ) {
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
            for ($i = 0; $i < $n; ++$i) {
                $x = $x->rightSkipWhitespace();
            }

            return $x;
        } catch (ZipperException) {
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

        if ($left->isLineBreak()) {
            return true;
        }

        return $left->isComment();
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
