<?php

declare(strict_types=1);

namespace Phel\Compiler\Parser\ExpressionParser;

use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Lexer\Token;
use Phel\Compiler\Parser\Exceptions\KeywordParserException;
use Phel\Compiler\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Parser\ParserNode\BooleanNode;
use Phel\Compiler\Parser\ParserNode\KeywordNode;
use Phel\Compiler\Parser\ParserNode\NilNode;
use Phel\Compiler\Parser\ParserNode\NumberNode;
use Phel\Compiler\Parser\ParserNode\SymbolNode;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

final class AtomParser
{
    private const KEYWORD_REGEX = '/:(?<second_colon>:?)((?<namespace>[^\/]+)\/)?(?<keyword>[^\/]+)/';

    private GlobalEnvironmentInterface $globalEnvironment;

    public function __construct(GlobalEnvironmentInterface $globalEnvironment)
    {
        $this->globalEnvironment = $globalEnvironment;
    }

    public function parse(Token $token): AbstractAtomNode
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

        if (str_starts_with($word, ':')) {
            return $this->parseKeyword($token);
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

    private function parseKeyword(Token $token): KeywordNode
    {
        $word = $token->getCode();
        $isValid = preg_match(self::KEYWORD_REGEX, $word, $matches);

        if (!$isValid) {
            throw new KeywordParserException('This is not a valid keyword');
        }

        $isDualColon = $matches['second_colon'] === ':';
        $hasNamespace = $matches['namespace'] !== '';

        $namespace = null;
        if ($isDualColon && $hasNamespace) {
            // First case is a dual colon with a namespace alias
            // like ::foo/bar
            $alias = $matches['namespace'];
            $namespace = $this->globalEnvironment->resolveAlias($alias);
            if (!$namespace) {
                throw new KeywordParserException("Can not resolve alias '$alias' in keyword: $word");
            }
        } elseif ($isDualColon) {
            // Second case is a dual colon without a namespace alias
            // like ::bar
            $namespace = $this->globalEnvironment->getNs();
        } elseif ($hasNamespace) {
            // Second case is a single colon with a absolute namespace
            // like :foo/bar
            $namespace = $matches['namespace'];
        }

        $keyword = $namespace
          ? Keyword::createForNamespace($namespace, $matches['keyword'])
          : Keyword::create($matches['keyword']);

        return new KeywordNode(
            $word,
            $token->getStartLocation(),
            $token->getEndLocation(),
            $keyword
        );
    }
}
