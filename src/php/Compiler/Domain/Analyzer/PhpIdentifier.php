<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\ErrorCode;

use function preg_match;

/**
 * @internal
 */
final class PhpIdentifier
{
    public static function assertName(TypeInterface $symbol, string $name): void
    {
        if (preg_match('/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/D', $name) !== 1) {
            throw AnalyzerException::withLocation(
                'Invalid PHP identifier ' . $name . '. Use letters, digits and underscores, starting with a letter or underscore.',
                $symbol,
                errorCode: ErrorCode::INVALID_SPECIAL_FORM,
            );
        }
    }

    public static function assertClassName(TypeInterface $symbol, string $name): void
    {
        foreach (explode('\\', str_starts_with($name, '\\') ? substr($name, 1) : $name) as $part) {
            self::assertName($symbol, $part);
        }
    }
}
