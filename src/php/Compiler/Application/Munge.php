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
     * Encode a namespace into the backslash form used by PHP `namespace`
     * declarations and class FQNs. Translates dots to backslashes, then
     * mangles dashes via the namespace mapping.
     */
    public function encodePhpNs(string $str): string
    {
        return $this->encodeWithMap(str_replace('.', '\\', $str), $this->nsMapping);
    }

    /**
     * Encode a namespace into the dot form used as the runtime registry key
     * (`\Phel::addDefinition`, `setNs`, `*ns*`). Translates backslashes to
     * dots, then mangles dashes via the namespace mapping.
     */
    public function encodeRegistryKey(string $str): string
    {
        return $this->encodeWithMap(str_replace('\\', '.', $str), $this->nsMapping);
    }

    public function decodeNs(string $str): string
    {
        return $this->encodeWithMap($str, array_flip($this->nsMapping));
    }

    /**
     * Canonicalize a namespace string by translating backslash separators to
     * dot. The registry, runtime, and user-facing APIs key off the dot form;
     * pass user-supplied namespace strings through this before any registry
     * lookup or write.
     */
    public static function canonicalNs(string $str): string
    {
        return str_replace('\\', '.', $str);
    }

    /**
     * Convert an internal namespace string to its display form by translating
     * backslash separators to dot. Equivalent to {@see self::canonicalNs()}
     * since dot is now the canonical form; kept for API stability.
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
