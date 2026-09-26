<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Emitter\OutputEmitter;

use Phel\Lang\AbstractFn;
use Phel\Lang\Registry;
use ReflectionMethod;

/**
 * Whether a global fn defines a fixed arity of its own at a given count,
 * that is, whether its class overrides `AbstractFn::invokeArityN` rather than
 * inheriting the default that bounces into `__invoke`.
 *
 * The call-site shortcut asks this for a variadic multi-arity callee (`+`,
 * `<`, `str`, `conj` ...), whose def meta cannot say which counts are fixed:
 * `(+ a b)` should reach `invokeArity2`, `(+ a b c)` the variadic arm through
 * `__invoke`. It reads the definition the compiling process already holds,
 * as the by-reference probe in `SpliceableBody` does. The answer only picks
 * the faster of two correct calls: a global redefined later is still called
 * correctly, the inherited `invokeArityN` forwards to `__invoke`.
 *
 * @internal
 */
final class FixedAritySlots
{
    /** @var array<string, array<int, bool>> class name => arity => declares */
    private static array $declared = [];

    private function __construct() {}

    public static function declares(string $ns, string $name, int $arity): bool
    {
        $fn = Registry::getInstance()->getDefinition($ns, $name);
        if (!$fn instanceof AbstractFn) {
            return false;
        }

        $class = $fn::class;

        return self::$declared[$class][$arity]
            ??= new ReflectionMethod($fn, 'invokeArity' . $arity)->getDeclaringClass()->getName() !== AbstractFn::class;
    }
}
