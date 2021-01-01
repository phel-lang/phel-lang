<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Parser\ParserNode\BooleanNodeAbstract;
use Phel\Compiler\Parser\ParserNode\KeywordNodeAbstract;
use Phel\Compiler\Parser\ParserNode\NilNodeAbstract;
use Phel\Compiler\Parser\ParserNode\NumberNodeAbstract;
use Phel\Compiler\Parser\ParserNode\SymbolNodeAbstract;
use Phel\Compiler\Token;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

final class AtomParser
{
    public function parse(Token $token): AbstractAtomNode
    {
        $word = $token->getCode();

        if ($word === 'true') {
            return new BooleanNodeAbstract($word, $token->getStartLocation(), $token->getEndLocation(), true);
        }

        if ($word === 'false') {
            return new BooleanNodeAbstract($word, $token->getStartLocation(), $token->getEndLocation(), false);
        }

        if ($word === 'nil') {
            return new NilNodeAbstract($word, $token->getStartLocation(), $token->getEndLocation(), null);
        }

        if (strpos($word, ':') === 0) {
            return new KeywordNodeAbstract(
                $word,
                $token->getStartLocation(),
                $token->getEndLocation(),
                new Keyword(substr($word, 1))
            );
        }

        if (preg_match('/^([+-])?0[bB][01]+(_[01]+)*$/', $word, $matches)) {
            // binary numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNodeAbstract(
                $word,
                $token->getStartLocation(),
                $token->getEndLocation(),
                $sign * bindec(str_replace('_', '', $word))
            );
        }

        if (preg_match('/^([+-])?0[xX][0-9a-fA-F]+(_[0-9a-fA-F]+)*$/', $word, $matches)) {
            // hexdecimal numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNodeAbstract(
                $word,
                $token->getStartLocation(),
                $token->getEndLocation(),
                $sign * hexdec(str_replace('_', '', $word))
            );
        }

        if (preg_match('/^([+-])?0[0-7]+(_[0-7]+)*$/', $word, $matches)) {
            // octal numbers
            $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
            return new NumberNodeAbstract(
                $word,
                $token->getStartLocation(),
                $token->getEndLocation(),
                $sign * octdec(str_replace('_', '', $word))
            );
        }

        if (is_numeric($word)) {
            return new NumberNodeAbstract($word, $token->getStartLocation(), $token->getEndLocation(), $word + 0);
        }

        return new SymbolNodeAbstract($word, $token->getStartLocation(), $token->getEndLocation(), Symbol::create($word));
    }
}
