<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\TypeAnalyzer\SpecialForm;

use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

use function array_map;
use function implode;
use function in_array;

/**
 * The return type a method implementing an interface method must declare, as
 * a `:tag` string. A tentative type counts too: PHP raises a deprecation on
 * an implementation that leaves it out.
 *
 * @internal
 */
final class InterfaceMethodReturnType
{
    public static function of(ReflectionMethod $method): ?string
    {
        $type = $method->getReturnType() ?? $method->getTentativeReturnType();
        if (!$type instanceof ReflectionType) {
            return null;
        }

        $declaringClass = $method->getDeclaringClass()->getName();

        if ($type instanceof ReflectionNamedType
            && $type->allowsNull()
            && !in_array($type->getName(), ['mixed', 'null'], true)
        ) {
            return '?' . self::render($type, $declaringClass);
        }

        return self::render($type, $declaringClass);
    }

    private static function render(ReflectionType $type, string $declaringClass): string
    {
        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                static fn(ReflectionType $member): string => $member instanceof ReflectionIntersectionType
                    ? '(' . self::render($member, $declaringClass) . ')'
                    : self::render($member, $declaringClass),
                $type->getTypes(),
            ));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map(
                static fn(ReflectionType $member): string => self::render($member, $declaringClass),
                $type->getTypes(),
            ));
        }

        if (!$type instanceof ReflectionNamedType) {
            return (string) $type;
        }

        $name = $type->getName();
        if ($name === 'self') {
            // The implementing class is not the interface, so `self` there
            // would narrow the contract to that one class.
            return '\\' . $declaringClass;
        }

        if ($type->isBuiltin() || $name === 'static') {
            return $name;
        }

        return '\\' . $name;
    }
}
