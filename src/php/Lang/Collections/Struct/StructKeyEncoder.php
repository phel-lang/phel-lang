<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Struct;

/**
 * Translates a struct key (Keyword name) to the PHP property name a defstruct
 * emits. Mirrors the encoding used by the compiler's name-mangling so structs
 * can resolve fields at runtime without depending on the Compiler module.
 *
 * The two encoders are kept in lockstep by tests; the mapping is part of the
 * on-disk format and effectively frozen.
 */
final readonly class StructKeyEncoder
{
    private const array MAPPING = [
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

    public function encode(string $name): string
    {
        if ($name === 'this') {
            return '__phel_this';
        }

        return str_replace(
            array_keys(self::MAPPING),
            array_values(self::MAPPING),
            $name,
        );
    }
}
