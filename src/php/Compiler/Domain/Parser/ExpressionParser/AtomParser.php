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
    private const REGEX_KEYWORD = '/:(?<second_colon>:?)((?<namespace>[^\/]+)\/)?(?<keyword>[^\/]+)/';

    private const REGEX_BINARY_NUMBER = '/^([+-])?0[bB]([01]+(?:_[01]+)*)$/';

    private const REGEX_HEXADECIMAL_NUMBER = '/^([+-])?0[xX]([0-9a-fA-F]+(?:_[0-9a-fA-F]+)*)$/';

    private const REGEX_OCTAL_NUMBER = '/^([+-])?0([0-7]+(?:_[0-7]+)*)$/';

    private const REGEX_DECIMAL_NUMBER = '/^(?:([+-])?\d+(_\d+)*[\.(_\d+]?|0)$/';

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
            return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $word + 0);
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

        $keyword = ($namespace !== null && $namespace !== '')
            ? Keyword::createForNamespace($namespace, $matches['keyword'])
            : Keyword::create($matches['keyword']);

        return new KeywordNode(
            $word,
            $token->getStartLocation(),
            $token->getEndLocation(),
            $keyword,
        );
    }

    private function parseBinaryNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $unsignedInteger = $matches[2] ?? $word;

        return new NumberNode(
            $word,
            $token->getStartLocation(),
            $token->getEndLocation(),
            $sign * bindec(str_replace('_', '', $unsignedInteger)),
        );
    }

    private function parseHexadecimalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $unsignedInteger = $matches[2] ?? $word;

        return new NumberNode(
            $word,
            $token->getStartLocation(),
            $token->getEndLocation(),
            $sign * hexdec(str_replace('_', '', $unsignedInteger)),
        );
    }

    private function parseOctalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $unsignedInteger = $matches[2] ?? $word;

        return new NumberNode(
            $word,
            $token->getStartLocation(),
            $token->getEndLocation(),
            $sign * octdec(str_replace('_', '', $unsignedInteger)),
        );
    }

    private function parseDecimalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;

        return new NumberNode(
            $word,
            $token->getStartLocation(),
            $token->getEndLocation(),
            $sign * (int)str_replace('_', '', $word),
        );
    }
}
