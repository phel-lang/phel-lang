<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Parser\ExpressionParser;

use Phel\Compiler\Domain\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Domain\Lexer\Token;
use Phel\Compiler\Domain\Parser\Exceptions\KeywordParserException;
use Phel\Compiler\Domain\Parser\Exceptions\ZeroDenominatorRatioParserException;
use Phel\Compiler\Domain\Parser\ParserNode\AbstractAtomNode;
use Phel\Compiler\Domain\Parser\ParserNode\BooleanNode;
use Phel\Compiler\Domain\Parser\ParserNode\KeywordNode;
use Phel\Compiler\Domain\Parser\ParserNode\NilNode;
use Phel\Compiler\Domain\Parser\ParserNode\NumberNode;
use Phel\Compiler\Domain\Parser\ParserNode\SymbolNode;
use Phel\Lang\BigInteger;
use Phel\Lang\Keyword;
use Phel\Lang\Rational;
use Phel\Lang\Symbol;

use function is_float;
use function ord;
use function sprintf;
use function strlen;

final readonly class AtomParser
{
    private const string REGEX_KEYWORD = '/:(?<second_colon>:?)((?<namespace>[^\/]+)\/)?(?<keyword>[^\/]+)/';

    private const string REGEX_BINARY_NUMBER = '/^([+-])?0[bB]([01]+(?:_[01]+)*)$/';

    private const string REGEX_HEXADECIMAL_NUMBER = '/^([+-])?0[xX]([0-9a-fA-F]+(?:_[0-9a-fA-F]+)*)$/';

    private const string REGEX_OCTAL_NUMBER = '/^([+-])?0([0-7]+(?:_[0-7]+)*)$/';

    private const string REGEX_RADIX_NUMBER = '/^([+-])?(2|[3-9]|[12]\d|3[0-6])[rR]([0-9a-zA-Z]+(?:_[0-9a-zA-Z]+)*)$/';

    /**
     * Clojure-style arbitrary-precision integer literal, e.g. `1N`, `-123N`,
     * `1_000_000N`. Phel has no first-class BigInt, so the `N` suffix is
     * stripped and the value is parsed as a plain PHP int — callers that
     * need values beyond `PHP_INT_MAX` must use an explicit library.
     */
    private const string REGEX_BIGINT_LITERAL = '/^([+-]?\d+(?:_\d+)*)N$/';

    /**
     * Clojure-style arbitrary-precision decimal literal, e.g. `1.5M`, `0M`,
     * `-123.456M`, `1.5e3M`. Phel has no first-class BigDecimal, so the `M`
     * suffix is stripped and the value is parsed as a plain PHP float —
     * callers that need arbitrary-precision decimals must use an explicit
     * library.
     */
    private const string REGEX_BIGDEC_LITERAL = '/^([+-]?\d+(?:_\d+)*(?:\.\d+(?:_\d+)*)?(?:[eE][+-]?\d+)?)M$/';

    /**
     * Ratio literal, e.g. `1/2`, `-3/4`, `0/5`. Parsed into an exact
     * {@see Rational} (or collapsed int / BigInteger when integral).
     * Zero denominators are rejected at parse time.
     */
    private const string REGEX_RATIO_LITERAL = '/^([+-]?\d+(?:_\d+)*)\/(\d+(?:_\d+)*)$/';

    private const string REGEX_DECIMAL_NUMBER = '/^(?:([+-])?\d+(_\d+)*[\.(_\d+]?|0)$/';

    public function __construct(private GlobalEnvironmentInterface $globalEnvironment) {}

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

        if (preg_match(self::REGEX_RADIX_NUMBER, $word, $matches)
            && $this->isValidDigitsForBase($matches[3], (int) $matches[2])
        ) {
            return $this->parseRadixNumber($matches, $word, $token);
        }

        if (preg_match(self::REGEX_BIGINT_LITERAL, $word, $matches)) {
            return $this->parseBigintLiteral($matches, $word, $token);
        }

        if (preg_match(self::REGEX_BIGDEC_LITERAL, $word, $matches)) {
            return $this->parseBigdecLiteral($matches, $word, $token);
        }

        if (preg_match(self::REGEX_RATIO_LITERAL, $word, $matches)) {
            return $this->parseRatioLiteral($matches, $word, $token);
        }

        if (is_numeric($word)) {
            $value = strpbrk($word, '.eE') !== false
                ? (float) $word
                : $this->parseIntegerWithOverflowFallback($word);

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
        $unsignedInteger = (string) ($matches[2] ?? $word);
        $value = bindec(str_replace('_', '', $unsignedInteger));

        if ($sign === -1) {
            $value = $this->normalizeNegativeOverflow(-$value);
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    private function parseHexadecimalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $unsignedInteger = (string) ($matches[2] ?? $word);
        $value = hexdec(str_replace('_', '', $unsignedInteger));

        if ($sign === -1) {
            $value = $this->normalizeNegativeOverflow(-$value);
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    private function parseOctalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $unsignedInteger = (string) ($matches[2] ?? $word);
        $value = octdec(str_replace('_', '', $unsignedInteger));

        if ($sign === -1) {
            $value = $this->normalizeNegativeOverflow(-$value);
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    /**
     * Parses Clojure-style radix literals of the form `NrXXX` where `N` is the
     * base (2–36) and `XXX` are digits valid for that base (case-insensitive
     * for bases greater than 10). Examples: `2r1111`, `16rFF`, `36rZZ`.
     */
    private function parseRadixNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $base = (int) $matches[2];
        $digits = str_replace('_', '', (string) $matches[3]);

        // `base_convert` returns a string; for values that fit, this is the
        // decimal integer representation. For values that overflow PHP_INT_MAX
        // it falls back to a scientific-notation string (cast to float below).
        $decimal = base_convert($digits, $base, 10);
        $value = str_contains($decimal, '.') || str_contains($decimal, 'E')
            ? (float) $decimal
            : (int) $decimal;

        if ($sign === -1) {
            $value = $this->normalizeNegativeOverflow(-$value);
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    /**
     * Explicit per-digit validation against the base. `base_convert` is
     * permissive: it silently treats out-of-range digits as zero, so we must
     * reject them ourselves to keep the radix parser strict.
     */
    private function isValidDigitsForBase(string $digits, int $base): bool
    {
        $digits = strtolower(str_replace('_', '', $digits));
        $length = strlen($digits);
        for ($i = 0; $i < $length; ++$i) {
            $char = $digits[$i];
            if ($char >= '0' && $char <= '9') {
                $digitValue = ord($char) - ord('0');
            } elseif ($char >= 'a' && $char <= 'z') {
                $digitValue = ord($char) - ord('a') + 10;
            } else {
                return false;
            }

            if ($digitValue >= $base) {
                return false;
            }
        }

        return true;
    }

    /**
     * When a bin/hex/oct literal equals the 64-bit minimum (e.g. `-0x8000000000000000`),
     * `bindec`/`hexdec`/`octdec` silently overflow the unsigned magnitude to a float.
     * Negating that float yields `(float) PHP_INT_MIN`, which the emitter then writes
     * as `-9223372036854775808.0` — a literal PHP itself cannot parse. Clamp that
     * single representable edge case back to the actual int `PHP_INT_MIN`.
     */
    private function normalizeNegativeOverflow(float|int $value): float|int
    {
        if (is_float($value) && $value === (float) PHP_INT_MIN) {
            return PHP_INT_MIN;
        }

        return $value;
    }

    /**
     * Casts a numeric integer literal to PHP `int`, falling back to `float`
     * when the literal exceeds `PHP_INT_MAX` / `PHP_INT_MIN`. Without this
     * fallback PHP's `(int)` cast silently clamps oversize literals to the
     * platform integer bound, so a literal like `99999999999999999999` would
     * compare equal to `PHP_INT_MAX` instead of preserving its magnitude.
     */
    private function parseIntegerWithOverflowFallback(string $word): float|int
    {
        $intValue = (int) $word;
        $magnitude = ltrim($word, '+-0') ?: '0';
        $canonical = ($word[0] === '-' && $magnitude !== '0' ? '-' : '') . $magnitude;

        return ((string) $intValue === $canonical) ? $intValue : (float) $word;
    }

    private function parseDecimalNumber(array $matches, string $word, Token $token): NumberNode
    {
        $sign = (isset($matches[1]) && $matches[1] === '-') ? -1 : 1;
        $value = (int) str_replace('_', '', $word);

        if ($sign === -1) {
            $value = -$value;
        }

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    /**
     * Parses a Clojure-style `N`-suffixed integer literal. The `N` marker
     * is stripped and the remainder is parsed as a plain PHP int. Phel has
     * no first-class arbitrary-precision integer type — values beyond
     * `PHP_INT_MAX` will overflow exactly as with an unsuffixed literal.
     * The suffix is accepted purely for `.cljc` source compatibility.
     */
    private function parseBigintLiteral(array $matches, string $word, Token $token): NumberNode
    {
        $value = (int) str_replace('_', '', (string) $matches[1]);

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    /**
     * Parses a Clojure-style `M`-suffixed decimal literal. The `M` marker
     * is stripped and the remainder is parsed as a plain PHP float. Phel
     * has no first-class arbitrary-precision decimal type — values beyond
     * IEEE-754 precision will round exactly as with an unsuffixed literal.
     * The suffix is accepted purely for `.cljc` source compatibility.
     */
    private function parseBigdecLiteral(array $matches, string $word, Token $token): NumberNode
    {
        $value = (float) str_replace('_', '', (string) $matches[1]);

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }

    /**
     * Parses a ratio literal `N/M` into an exact value. The result is a
     * {@see Rational} when irreducible, a {@see BigInteger} when the
     * normalised numerator no longer fits in a PHP int, otherwise a
     * native int. Zero denominators are rejected at parse time.
     */
    private function parseRatioLiteral(array $matches, string $word, Token $token): NumberNode
    {
        $numerator = BigInteger::fromString(str_replace('_', '', (string) $matches[1]));
        $denominator = BigInteger::fromString(str_replace('_', '', (string) $matches[2]));

        if ($denominator->isZero()) {
            throw new ZeroDenominatorRatioParserException(
                sprintf('Ratio literal denominator cannot be zero: %s', $word),
            );
        }

        $value = Rational::create($numerator, $denominator);

        return new NumberNode($word, $token->getStartLocation(), $token->getEndLocation(), $value);
    }
}
