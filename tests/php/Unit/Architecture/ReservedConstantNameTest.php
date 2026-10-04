<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function preg_match;

/**
 * PHP 8.6 deprecates a class constant named `namespace` (any case), and only a
 * rename fixes it. A public one is part of the API, so the rename costs a major.
 */
final class ReservedConstantNameTest extends TestCase
{
    use ScansPhpSourcesTrait;

    public function test_no_class_constant_is_named_namespace(): void
    {
        $offenders = [];
        foreach ($this->phpFilesIn('src/php') as $relative => $contents) {
            if (preg_match('/\bconst\s+(?:\??[\w\\\\|]+\s+)?namespace\s*=/i', $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        self::assertSame([], $offenders, 'PHP 8.6 deprecates a class constant named "namespace".');
    }
}
