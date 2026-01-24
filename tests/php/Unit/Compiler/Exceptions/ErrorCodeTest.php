<?php

declare(strict_types=1);

namespace PhelTest\Unit\Compiler\Exceptions;

use Phel\Compiler\Domain\Exceptions\ErrorCode;
use PHPUnit\Framework\TestCase;

use function count;

final class ErrorCodeTest extends TestCase
{
    public function test_error_code_values_are_unique(): void
    {
        $values = array_map(static fn (ErrorCode $code): string => $code->value, ErrorCode::cases());

        self::assertCount(count($values), array_unique($values), 'Error codes should be unique');
    }

    public function test_analyzer_error_codes_start_with_001_099(): void
    {
        self::assertSame('PHEL001', ErrorCode::UNDEFINED_SYMBOL->value);
        self::assertSame('PHEL002', ErrorCode::ARITY_ERROR->value);
        self::assertSame('PHEL005', ErrorCode::MACRO_EXPANSION_ERROR->value);
    }

    public function test_parser_error_codes_start_with_100_199(): void
    {
        self::assertSame('PHEL100', ErrorCode::UNTERMINATED_LIST->value);
        self::assertSame('PHEL110', ErrorCode::UNEXPECTED_TOKEN->value);
    }

    public function test_reader_error_codes_start_with_200_299(): void
    {
        self::assertSame('PHEL200', ErrorCode::INVALID_QUOTE->value);
    }

    public function test_lexer_error_codes_start_with_300_399(): void
    {
        self::assertSame('PHEL300', ErrorCode::INVALID_CHARACTER->value);
    }
}
