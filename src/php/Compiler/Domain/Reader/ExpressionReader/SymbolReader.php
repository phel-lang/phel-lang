<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Reader\ExpressionReader;

use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Lang\Symbol;

final class SymbolReader
{
    private const array NUMBERED_PATTERNS = [
        '$' => '/\$([1-9]\d*)/',
        '%' => '/%([1-9]\d*)/',
    ];

    /**
     * @param array<int,Symbol>|null &$fnArgs
     */
    public function read(SymbolNode $node, ?array &$fnArgs, string $placeholderPrefix = '$'): Symbol
    {
        $symbol = $this->createSymbol($node, $fnArgs, $placeholderPrefix);

        $symbol->setStartLocation($node->getStartLocation());
        $symbol->setEndLocation($node->getEndLocation());

        return $symbol;
    }

    /**
     * @param array<int,Symbol>|null &$fnArgs
     */
    private function createSymbol(SymbolNode $node, ?array &$fnArgs, string $prefix): Symbol
    {
        if ($fnArgs === null) {
            return $node->getValue();
        }

        $word = $node->getValue()->getName();

        if ($word === $prefix) {
            if (isset($fnArgs[1])) {
                return Symbol::create($fnArgs[1]->getName());
            }

            $sym = Symbol::gen('__short_fn_1_');
            $fnArgs[1] = $sym;
            return $sym;
        }

        if ($word === $prefix . '&') {
            if (isset($fnArgs[0])) {
                return Symbol::create($fnArgs[0]->getName());
            }

            $sym = Symbol::gen('__short_fn_rest_');
            $fnArgs[0] = $sym;
            return $sym;
        }

        if (preg_match(self::NUMBERED_PATTERNS[$prefix], $word, $matches)) {
            $number = (int) $matches[1];
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
