<?php

declare(strict_types=1);

namespace PhelTest\Unit\Api\Application\Fixtures;

use function str_repeat;

/**
 * Fixture with an explicit phpdoc so reflection-backed signature help can be
 * exercised against documented, multi-parameter methods (internal PHP classes
 * usually carry neither).
 */
final class SignatureFixture
{
    /**
     * Greets a person a number of times.
     *
     * @param string $name  the person to greet
     * @param int    $times how many times to repeat the greeting
     */
    public function greet(string $name, int $times = 1): string
    {
        return str_repeat($name, $times);
    }
}
