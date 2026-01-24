<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Exceptions;

/**
 * Error codes for Phel compiler errors.
 * These codes can be used for documentation lookup.
 *
 * Code ranges:
 * - PHEL001-099: Analyzer errors (undefined symbol, arity, type errors)
 * - PHEL100-199: Parser errors (unterminated, unexpected token)
 * - PHEL200-299: Reader errors (quote, splice issues)
 * - PHEL300-399: Lexer errors (invalid characters, unterminated strings)
 */
enum ErrorCode: string
{
    // Analyzer errors (PHEL001-099)
    case UNDEFINED_SYMBOL = 'PHEL001';
    case ARITY_ERROR = 'PHEL002';
    case TYPE_ERROR = 'PHEL003';
    case DEF_NOT_ALLOWED = 'PHEL004';
    case MACRO_EXPANSION_ERROR = 'PHEL005';
    case INLINE_EXPANSION_ERROR = 'PHEL006';
    case INVALID_SPECIAL_FORM = 'PHEL007';
    case BINDING_ERROR = 'PHEL008';
    case INTERFACE_ERROR = 'PHEL009';
    case RECUR_ERROR = 'PHEL010';

    // Parser errors (PHEL100-199)
    case UNTERMINATED_LIST = 'PHEL100';
    case UNTERMINATED_VECTOR = 'PHEL101';
    case UNTERMINATED_MAP = 'PHEL102';
    case UNTERMINATED_TABLE = 'PHEL103';
    case UNEXPECTED_TOKEN = 'PHEL110';
    case PARSER_ERROR = 'PHEL120';

    // Reader errors (PHEL200-299)
    case INVALID_QUOTE = 'PHEL200';
    case INVALID_UNQUOTE = 'PHEL201';
    case INVALID_SPLICE = 'PHEL202';
    case READER_ERROR = 'PHEL210';

    // Lexer errors (PHEL300-399)
    case INVALID_CHARACTER = 'PHEL300';
    case UNTERMINATED_STRING = 'PHEL301';
    case LEXER_ERROR = 'PHEL310';
}
