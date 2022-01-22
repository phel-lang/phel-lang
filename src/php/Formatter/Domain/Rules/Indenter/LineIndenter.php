<?php

declare(strict_types=1);

namespace Phel\Formatter\Domain\Rules\Indenter;

use Phel\Compiler\Parser\ParserNode\InnerNodeInterface;
use Phel\Formatter\Domain\Rules\Zipper\ParseTreeZipper;

final class LineIndenter implements IndenterInterface
{
    public function getMargin(ParseTreeZipper $loc, int $indentWidth): ?int
    {
        return strlen($this->lastLineInString($this->priorLineString($loc)));
    }

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

    private function lastLineInString(string $s): string
    {
        $pos = strrpos($s, "\n");
        if ($pos !== false) {
            return substr($s, $pos + 1);
        }

        return $s;
    }
}
