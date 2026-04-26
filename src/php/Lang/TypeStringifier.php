<?php

declare(strict_types=1);

namespace Phel\Lang;

use Throwable;

use function get_debug_type;

final class TypeStringifier
{
    /** @var callable(TypeInterface):string|null */
    private static $stringifier;

    /**
     * @param callable(TypeInterface):string $stringifier
     */
    public static function install(callable $stringifier): void
    {
        self::$stringifier = $stringifier;
    }

    public static function toString(TypeInterface $value): string
    {
        if (self::$stringifier !== null) {
            return (self::$stringifier)($value);
        }

        try {
            $printerClass = implode('\\', ['Phel', 'Printer', 'Printer']);
            if (class_exists($printerClass)) {
                return $printerClass::readable()->print($value);
            }
        } catch (Throwable) {
        }

        return '#object[' . get_debug_type($value) . ']';
    }
}
