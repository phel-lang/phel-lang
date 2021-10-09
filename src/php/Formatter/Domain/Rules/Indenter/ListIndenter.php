<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Compiler\Parser\ParserNode\TriviaNodeInterface;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;

final class ListIndenter implements IndenterInterface
{
    private LineIndenter $lineIndenter;

    public function __construct()
    {
        $this->lineIndenter = new LineIndenter();
    }

    public function getMargin(ParseTreeZipper $loc, int $indentWidth): ?int
    {
        $l = $loc->leftMost();
        if ($this->indexOf($loc) > 1) {
            $l = $l->rightSkipWhitespace();
        }

        return $this->lineIndenter->getMargin($l, $indentWidth);
    }

    private function indexOf(ParseTreeZipper $loc): int
    {
        $lefts = $loc->lefts();
        $i = 0;
        foreach ($lefts as $left) {
            if (!$left instanceof TriviaNodeInterface) {
                $i++;
            }
        }

        return $i;
    }
}
