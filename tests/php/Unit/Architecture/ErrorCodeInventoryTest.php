<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use Phel\Shared\Exceptions\ErrorCode;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * `ErrorCode` is a user-visible surface: a code the user reads must name an
 * error Phel actually raises, and must be explainable. These tests keep the
 * enum, the catalog and the pages under `docs/errors/` in step.
 */
final class ErrorCodeInventoryTest extends TestCase
{
    use ScansPhpSourcesTrait;

    private const string ENUM_FILE = 'Shared/Exceptions/ErrorCode.php';

    public function test_every_error_code_has_a_production_caller(): void
    {
        $sources = $this->phpFilesIn('src/php');
        unset($sources[self::ENUM_FILE]);

        $orphans = [];

        foreach (ErrorCode::cases() as $case) {
            $pattern = sprintf('/\bErrorCode::%s\b/', $case->name);

            foreach ($sources as $contents) {
                if (preg_match($pattern, $contents) === 1) {
                    continue 2;
                }
            }

            $orphans[] = sprintf('%s (%s)', $case->name, $case->value);
        }

        self::assertSame([], $orphans, sprintf(
            "These error codes have no production caller: %s.\nWire each one to the error it names or delete the case.",
            implode(', ', $orphans),
        ));
    }
}
