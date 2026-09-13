<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

/**
 * Error codes for Phel compiler and runtime errors.
 * These codes are documented under docs/errors/ and printed by phel explain.
 *
 * Code ranges:
 * - PHEL001-099: Analyzer errors (undefined symbol, arity, type errors)
 * - PHEL100-199: Parser errors (unterminated, unexpected token)
 * - PHEL200-299: Reader errors (quote, splice issues)
 * - PHEL300-399: Lexer errors (invalid characters, unterminated strings)
 * - PHEL400-499: Runtime errors (not callable, arity, type, bounds, division by zero)
 */
enum ErrorCode: string
{
    // Analyzer errors (PHEL001-099)
    case UNDEFINED_SYMBOL = 'PHEL001';
    case ARITY_ERROR = 'PHEL002';
    case TYPE_ERROR = 'PHEL003';
    case DUPLICATE_DEFINITION = 'PHEL004';
    case MACRO_EXPANSION_ERROR = 'PHEL005';
    case INLINE_EXPANSION_ERROR = 'PHEL006';
    case INVALID_SPECIAL_FORM = 'PHEL007';
    case BINDING_ERROR = 'PHEL008';
    case INTERFACE_ERROR = 'PHEL009';
    case RECUR_ERROR = 'PHEL010';
    case NOT_CALLABLE = 'PHEL011';
    case SUPERSEDED_FORM = 'PHEL012';

    // Parser errors (PHEL100-199)
    case UNTERMINATED_LIST = 'PHEL100';
    case UNTERMINATED_VECTOR = 'PHEL101';
    case UNTERMINATED_MAP = 'PHEL102';
    case UNTERMINATED_TABLE = 'PHEL103';
    case UNEXPECTED_TOKEN = 'PHEL110';
    case PARSER_ERROR = 'PHEL120';

    // Reader errors (PHEL200-299)
    case INVALID_SPLICE = 'PHEL202';
    case READER_ERROR = 'PHEL210';

    // Lexer errors (PHEL300-399)
    case UNTERMINATED_STRING = 'PHEL301';
    case LEXER_ERROR = 'PHEL310';

    // Runtime errors (PHEL400-499)
    case RUNTIME_NOT_CALLABLE = 'PHEL400';
    case RUNTIME_ARITY_ERROR = 'PHEL401';
    case RUNTIME_TYPE_ERROR = 'PHEL402';
    case INDEX_OUT_OF_BOUNDS = 'PHEL403';
    case DIVISION_BY_ZERO = 'PHEL404';
}
