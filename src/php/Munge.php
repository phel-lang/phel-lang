<?php

namespace Phel;

class Munge
{
    protected static $mungeNsMapping = [
        '-' => '_',
    ];

    /**
     * @var array
     */
    protected static $mungeMapping = [
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
        '#' => "_SHARP_",
        '\'' => "_SINGLEQUOTE_",
        '"' => "_DOUBLEQUOTE_",
        '%' => "_PERCENT_",
        '^' => "_CARET_",
        '&' => "_AMPERSAND_",
        '*' => "_STAR_",
        '|' => "_BAR_",
        '{' => "_LBRACE_",
        '}' => "_RBRACE_",
        '[' => "_LBRACK_",
        ']' => "_RBRACK_",
        '/' => "_SLASH_",
        '\\' => "_BSLASH_",
        '?' => "_QMARK_"
    ];

    public static function encode(string $s): string
    {
        if ($s == 'this') {
            return '__phel_this';
        } else {
            return self::encodeWithMap($s, self::$mungeMapping);
        }
    }

    public static function encodeNs(string $s): string
    {
        return self::encodeWithMap($s, self::$mungeNsMapping);
    }

    public static function decodeNs(string $s): string
    {
        return self::encodeWithMap($s, array_flip(self::$mungeNsMapping));
    }

    private static function encodeWithMap(string $s, $mapping): string
    {
        return str_replace(
            array_keys($mapping),
            array_values($mapping),
            $s
        );
    }
}
