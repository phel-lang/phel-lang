<?php

declare(strict_types=1);

namespace PhelTest\Support;

use function class_alias;
use function class_exists;
use function define;
use function defined;

trait DefinesClassConstantCollisionTrait
{
    /**
     * Makes one all-caps name both an existing PHP class and a defined global
     * constant, the collision the bare-host-symbol fallback has to settle.
     *
     * Aliases and constants are process-global and cannot be undone, so each
     * caller owns a distinct `$name` and the guards make a re-entry harmless.
     */
    private static function defineClassConstantCollision(
        string $name,
        string $classTarget,
        int $constantValue,
    ): void {
        if (!class_exists($name, false)) {
            class_alias($classTarget, $name);
        }

        if (!defined($name)) {
            define($name, $constantValue);
        }
    }
}
