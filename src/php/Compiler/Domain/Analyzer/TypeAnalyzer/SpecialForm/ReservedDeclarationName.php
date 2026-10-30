<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use Phel\Compiler\Domain\Analyzer\Exceptions\AnalyzerException;
use Phel\Compiler\Domain\Analyzer\PhpIdentifier;
use Phel\Lang\Symbol;
use Phel\Shared\Exceptions\ErrorCode;
use Phel\Shared\Munge;

use function in_array;
use function strtolower;

/**
 * @internal
 */
final class ReservedDeclarationName
{
    private const array TYPES = [
        '__halt_compiler', '__class__', '__dir__', '__file__', '__function__', '__line__',
        '__method__', '__namespace__', '__property__', '__trait__',
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
        'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo',
        'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif',
        'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'false', 'final', 'finally',
        'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements',
        'include', 'include_once', 'instanceof', 'insteadof', 'int', 'interface', 'is',
        'isset', 'iterable', 'let', 'list', 'match', 'mixed', 'namespace', 'never', 'new',
        'null', 'object', 'or', 'parent', 'print', 'private', 'protected', 'public',
        'readonly', 'require', 'require_once', 'return', 'self', 'static', 'string',
        'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void',
        'while', 'xor', 'yield',
    ];

    public static function assertType(Symbol $name): void
    {
        PhpIdentifier::assertName($name, new Munge()->encode($name->getName()));
        self::assertAllowed($name, self::TYPES, ErrorCode::INVALID_SPECIAL_FORM);
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
