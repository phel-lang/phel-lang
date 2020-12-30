<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser;

use Phel\Compiler\Parser\ParserNode\AtomNode;
use Phel\Compiler\Parser\ParserNode\BooleanNode;
use Phel\Compiler\Parser\ParserNode\KeywordNode;
use Phel\Compiler\Parser\ParserNode\NilNode;
use Phel\Compiler\Parser\ParserNode\NumberNode;
use Phel\Compiler\Parser\ParserNode\SymbolNode;
use Phel\Compiler\Token;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

final class AtomParser
{
    public function parse(Token $token): AtomNode
    {
        $word = $token->getCode();

        if ($word === 'true') {
            return new BooleanNode($word, $token->getStartLocation(), $token->getEndLocation(), true);
        }

        if ($word === 'false') {
            return new BooleanNode($word, $token->getStartLocation(), $token->getEndLocation(), false);
        }

        if ($word === 'nil') {
            return new NilNode($word, $token->getStartLocation(), $token->getEndLocation(), null);
        }

        if (strpos($word, ':') === 0) {
            return new KeywordNode(
                $word,
                $token->getStartLocation(),
                $token->getEndLocation(),
                new Keyword(substr($word, 1))
            );
        }

        if (preg_match('/^([+-])?0[bB][01]+(_[01]+)*$/', $word, $matches)) {
            // binary numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNode(
                $word,
                $token->getStartLocation(),
                $token->getEndLocation(),
                $sign * bindec(str_replace('_', '', $word))
            );
        }

        if (preg_match('/^([+-])?0[xX][0-9a-fA-F]+(_[0-9a-fA-F]+)*$/', $word, $matches)) {
            // hexdecimal numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNode(
                $word,
                $token->getStartLocation(),
                $token->getEndLocation(),
                $sign * hexdec(str_replace('_', '', $word))
            );
        }

        if (preg_match('/^([+-])?0[0-7]+(_[0-7]+)*$/', $word, $matches)) {
            // octal numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNode(
                $word,
                $token->getStartLocation(),
                $token->getEndLocation(),
                $sign * octdec(str_replace('_', '', $word))
            );
        }

        if (is_numeric($word)) {
            return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $word + 0);
        }

        return new SymbolNode($word, $token->getStartLocation(), $token->getEndLocation(), Symbol::create($word));
    }
}
