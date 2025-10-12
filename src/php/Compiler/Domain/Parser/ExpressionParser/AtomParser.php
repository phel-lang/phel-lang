<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\Exceptions\KeywordParserException;
use Phel\Compiler\Domain\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Domain\Parser\ParserNode\BooleanNode;
use Phel\Compiler\Domain\Parser\ParserNode\KeywordNode;
use Phel\Compiler\Domain\Parser\ParserNode\NilNode;
use Phel\Compiler\Domain\Parser\ParserNode\NumberNode;
use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;

use function sprintf;

final readonly class AtomParser
{
    private const string REGEX_KEYWORD = '/:(?<second_colon>:?)((?<namespace>[^\/]+)\/)?(?<keyword>[^\/]+)/';

    private const string REGEX_BINARY_NUMBER = '/^([+-])?0[bB]([01]+(?:_[01]+)*)$/';

    private const string REGEX_HEXADECIMAL_NUMBER = '/^([+-])?0[xX]([0-9a-fA-F]+(?:_[0-9a-fA-F]+)*)$/';

    private const string REGEX_OCTAL_NUMBER = '/^([+-])?0([0-7]+(?:_[0-7]+)*)$/';

    private const string REGEX_DECIMAL_NUMBER = '/^(?:([+-])?\d+(_\d+)*[\.(_\d+]?|0)$/';

    public function __construct(private GlobalEnvironmentInterface $globalEnvironment)
    {
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

        if (preg_match(self::REGEX_BINARY_NUMBER, $word, $matches)) {
            return $this->parseBinaryNumber($matches, $word, $token);
        }

        if (preg_match(self::REGEX_HEXADECIMAL_NUMBER, $word, $matches)) {
            return $this->parseHexadecimalNumber($matches, $word, $token);
        }

        if (preg_match(self::REGEX_OCTAL_NUMBER, $word, $matches)) {
            return $this->parseOctalNumber($matches, $word, $token);
        }

        if (is_numeric($word)) {
            $value = strpbrk($word, '.eE') !== false ? (float)$word : (int)$word;

            return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
        }

        if (preg_match(self::REGEX_DECIMAL_NUMBER, $word, $matches)) {
            return $this->parseDecimalNumber($matches, $word, $token);
        }

        return new SymbolNode($word, $token->getStartLocation(), $token->getEndLocation(), Symbol::create($word));
    }

    private function parseKeyword(Token $token): KeywordNode
    {
        $word = $token->getCode();
        $isValid = preg_match(self::REGEX_KEYWORD, $word, $matches);

        if ($isValid === 0 || $isValid === false) {
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
            if ($namespace === null || $namespace === '') {
                throw new KeywordParserException(sprintf("Can not resolve alias '%s' in keyword: %s", $alias, $word));
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

        return new KeywordNode(
            $word,
            $token->getStartLocation(),
            $token->getEndLocation(),
            Keyword::create($matches['keyword'], $namespace),
        );
    }

    private function parseBinaryNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $unsignedInteger = (string)($matches[2] ?? $word);
        $value = bindec(str_replace('_', '', $unsignedInteger));

        if ($sign === -1) {
            $value = -$value;
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    private function parseHexadecimalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $unsignedInteger = (string)($matches[2] ?? $word);
        $value = hexdec(str_replace('_', '', $unsignedInteger));

        if ($sign === -1) {
            $value = -$value;
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    private function parseOctalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $unsignedInteger = (string)($matches[2] ?? $word);
        $value = octdec(str_replace('_', '', $unsignedInteger));

        if ($sign === -1) {
            $value = -$value;
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    private function parseDecimalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $value = (int)str_replace('_', '', $word);

        if ($sign === -1) {
            $value = -$value;
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }
}
