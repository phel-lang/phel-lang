<?php

declare(strict_types=1);

namespace Phel\Shared;

final readonly class Munge implements MungeInterface
{
    private const array NAMESPACE_MAPPING = [
        '-' => '_',
    ];

    private const array SYMBOL_MAPPING = [
        '-' => '_',
        '.' => '_DOT_',
        ':' => '_COLON_',
        '+' => '_PLUS_',
        '>' => '_GT_',
        '<' => '_LT_',
        '=' => '_EQ_',
        '~' => '_TILDE_',
        '!' => '_BANG_',
        '@' => '_CIRCA_',
        '#' => '_SHARP_',
        "'" => '_SINGLEQUOTE_',
        '"' => '_DOUBLEQUOTE_',
        '%' => '_PERCENT_',
        '^' => '_CARET_',
        '&' => '_AMPERSAND_',
        '*' => '_STAR_',
        '|' => '_BAR_',
        '{' => '_LBRACE_',
        '}' => '_RBRACE_',
        '[' => '_LBRACK_',
        ']' => '_RBRACK_',
        '/' => '_SLASH_',
        '\\' => '_BSLASH_',
        '?' => '_QMARK_',
        '$' => '_DOLLAR_',
    ];

    /**
     * @param array<string, string> $mapping
     * @param array<string, string> $nsMapping
     */
    public function __construct(
        private array $mapping = self::SYMBOL_MAPPING,
        private array $nsMapping = self::NAMESPACE_MAPPING,
    ) {}

    /**
     * Encodes a symbol name into a valid PHP identifier by replacing every
     * special character per the configured mapping. `this` is special-cased
     * because it is a reserved PHP variable name.
     */
    public function encode(string $str): string
    {
        if ($str === 'this') {
            return '__phel_this';
        }

        return $this->encodeWithMap($str, $this->mapping);
    }

    /**
     * Backslash form, used by PHP `namespace ...;` and class FQN emission.
     */
    public function encodePhpNs(string $str): string
    {
        return $this->encodeWithMap(str_replace('.', '\\', $str), $this->nsMapping);
    }

    /**
     * Dot form, used as the runtime registry key.
     */
    public function encodeRegistryKey(string $str): string
    {
        return $this->encodeWithMap(self::canonicalNs($str), $this->nsMapping);
    }

    /**
     * Inverse of the namespace encoding: maps an encoded namespace string back
     * to its canonical (dot) source form by applying the flipped mapping.
     */
    public function decodeNs(string $str): string
    {
        return $this->encodeWithMap($str, array_flip($this->nsMapping));
    }

    /**
     * Canonical (dot) form. Pass user-supplied namespace strings through this
     * before any registry lookup or write.
     */
    public static function canonicalNs(string $str): string
    {
        return str_replace('\\', '.', $str);
    }

    /**
     * Display form. Equivalent to {@see self::canonicalNs()}; kept as a
     * separate name so call sites read intent (display vs. canonicalize).
     */
    public static function displayNs(string $str): string
    {
        return self::canonicalNs($str);
    }

    /**
     * @psalm-param array<string, string> $mapping
     */
    private function encodeWithMap(string $str, array $mapping): string
    {
        return strtr($str, $mapping);
    }
}
