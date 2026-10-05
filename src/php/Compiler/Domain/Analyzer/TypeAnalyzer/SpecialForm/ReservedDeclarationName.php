<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;

use function in_array;
use function strtolower;

/**
 * @internal
 */
final class ReservedDeclarationName
{
    public static function assertType(Symbol $name): void
    {
        self::assertAllowed($name, ['let', 'is'], ErrorCode::INVALID_SPECIAL_FORM);
    }

    public static function assertConstant(Symbol $name): void
    {
        self::assertAllowed($name, ['let', 'is', 'namespace'], ErrorCode::INTERFACE_ERROR);
    }

    /**
     * @param list<string> $reserved
     */
    private static function assertAllowed(Symbol $name, array $reserved, ErrorCode $code): void
    {
        if (in_array(strtolower($name->getName()), $reserved, true)) {
            throw AnalyzerException::withLocation(
                'Declaration name ' . $name->getName() . ' is reserved PHP syntax. Choose another name.',
                $name,
                errorCode: $code,
            );
        }
    }
}
