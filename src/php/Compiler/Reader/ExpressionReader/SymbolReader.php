<?php

declare(strict_types=1);

namespace Phel\Compiler\Reader\ExpressionReader;

use Phel\Compiler\Parser\ParserNode\SymbolNode;
use Phel\Lang\Symbol;

final class SymbolReader
{
    /**
     * @param Symbol[]|null &$fnArgs
     */
    public function read(SymbolNode $node, ?array &$fnArgs): Symbol
    {
        $symbol = $this->createSymbol($node, $fnArgs);

        $symbol->setStartLocation($node->getStartLocation());
        $symbol->setEndLocation($node->getEndLocation());

        return $symbol;
    }

    /**
     * @param Symbol[]|null &$fnArgs
     */
    private function createSymbol(SymbolNode $node, ?array &$fnArgs): Symbol
    {
        if (null === $fnArgs) {
            return $node->getValue();
        }

        $word = $node->getValue()->getName();

        // Special case: We read an anonymous function
        if ($word === '$') {
            if (isset($fnArgs[1])) {
                return Symbol::create($fnArgs[1]->getName());
            }
            $sym = Symbol::gen('__short_fn_1_');
            $fnArgs[1] = $sym;
            return $sym;
        }

        if ($word === '$&') {
            if (isset($fnArgs[0])) {
                return Symbol::create($fnArgs[0]->getName());
            }
            $sym = Symbol::gen('__short_fn_rest_');
            $fnArgs[0] = $sym;
            return $sym;
        }

        if (preg_match('/\$([1-9][0-9]*)/', $word, $matches)) {
            $number = (int)$matches[1];
            if (isset($fnArgs[$number])) {
                return Symbol::create($fnArgs[$number]->getName());
            }
            $sym = Symbol::gen('__short_fn_' . $number . '_');
            $fnArgs[$number] = $sym;
            return $sym;
        }

        return $node->getValue();
    }
}
