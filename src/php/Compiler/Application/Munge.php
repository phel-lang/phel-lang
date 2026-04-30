<?php

declare(strict_types=1);

namespace Phel\Compiler\Application;

use Phel\Compiler\Domain\Emitter\OutputEmitter\MungeInterface;

final readonly class Munge implements MungeInterface
{
    private const array DEFAULT_NS_MAPPING = [
        '-' => '_',
    ];

    private const array DEFAULT_MAPPING = [
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

    public function __construct(
        private array $mapping = self::DEFAULT_MAPPING,
        private array $nsMapping = self::DEFAULT_NS_MAPPING,
    ) {}

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
        return str_replace(
            array_keys($mapping),
            array_values($mapping),
            $str,
        );
    }
}
