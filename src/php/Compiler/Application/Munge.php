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

    public function encodeNs(string $str): string
    {
        return $this->encodeWithMap(self::canonicalNs($str), $this->nsMapping);
    }

    public function decodeNs(string $str): string
    {
        return $this->encodeWithMap($str, array_flip($this->nsMapping));
    }

    /**
     * Canonicalize a namespace string by translating dot separators to
     * backslash. The registry, emitter, and analyzer key off the backslash
     * form; pass user-supplied namespace strings through this before any
     * registry lookup or write.
     */
    public static function canonicalNs(string $str): string
    {
        return str_replace('.', '\\', $str);
    }

    /**
     * Convert an internal namespace string to its display form by translating
     * backslash separators to dot. The dot form is the source-level separator
     * surfaced by user-facing APIs and printed output.
     */
    public static function displayNs(string $str): string
    {
        return str_replace('\\', '.', $str);
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
