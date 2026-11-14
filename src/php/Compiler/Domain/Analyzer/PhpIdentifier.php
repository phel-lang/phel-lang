<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Compiler\Domain\Analyzer\Ast\PhpVarNode;
use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\TypeInterface;
use Phel\Shared\Exceptions\ErrorCode;

use function in_array;
use function preg_match;
use function str_replace;

/**
 * @internal
 */
final class PhpIdentifier
{
    public static function assertReference(TypeInterface $symbol, string $name): void
    {
        if (in_array($name, PhpVarNode::INFIX_OPERATORS, true) || in_array($name, ['!', '~'], true)) {
            return;
        }

        if (str_starts_with($name, '$')) {
            self::assertName($symbol, substr($name, 1));

            return;
        }

        self::assertClassName($symbol, str_replace(['/', '.'], '\\', $name));
    }

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
