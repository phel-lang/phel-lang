<?php

declare(strict_types=1);

namespace Phel\Compiler\Emitter\OutputEmitter;

final class Munge implements MungeInterface
{
    private const DEFAULT_NS_MAPPING = [
        '-' => '_',
    ];

    private const DEFAULT_MAPPING = [
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
        '\'' => '_SINGLEQUOTE_',
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
    ];

    private array $mapping;
    private array $nsMapping;

    public function __construct(
        array $mapping = self::DEFAULT_MAPPING,
        array $nsMapping = self::DEFAULT_NS_MAPPING
    ) {
        $this->mapping = $mapping;
        $this->nsMapping = $nsMapping;
    }

    public function encode(string $str): string
    {
        if ($str === 'this') {
            return '__phel_this';
        }

        return $this->encodeWithMap($str, $this->mapping);
    }

    public function encodeNs(string $str): string
    {
        return $this->encodeWithMap($str, $this->nsMapping);
    }

    public function decodeNs(string $str): string
    {
        return $this->encodeWithMap($str, array_flip($this->nsMapping));
    }

    /**
     * @psalm-param array<string, string> $mapping
     */
    private function encodeWithMap(string $str, array $mapping): string
    {
        return str_replace(
            array_keys($mapping),
            array_values($mapping),
            $str
        );
    }
}
