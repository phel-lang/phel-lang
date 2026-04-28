<?php

declare(strict_types=1);

namespace PhelTest\Integration\Phel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function escapeshellarg;
use function proc_close;
use function proc_open;
use function sprintf;
use function stream_get_contents;

/**
 * Runs each file of `tests/phel/test/schema/*.phel` end-to-end so
 * `composer test-compiler` exercises the data-driven schema module.
 */
final class SchemaTest extends TestCase
{
    /**
     * @return iterable<string, list<string>>
     */
    public static function schemaFiles(): iterable
    {
        yield 'validate' => ['tests/phel/test/schema/validate.phel'];
        yield 'explain' => ['tests/phel/test/schema/explain.phel'];
        yield 'coerce' => ['tests/phel/test/schema/coerce.phel'];
        yield 'generate' => ['tests/phel/test/schema/generate.phel'];
        yield 'instrument' => ['tests/phel/test/schema/instrument.phel'];
        yield 'registry' => ['tests/phel/test/schema/registry.phel'];
    }

    #[DataProvider('schemaFiles')]
    public function test_schema_suite_passes(string $relativePath): void
    {
        $repoRoot = dirname(__DIR__, 4);
        $command = sprintf(
            '%s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($repoRoot . '/bin/phel'),
            escapeshellarg('test') . ' ' . escapeshellarg($relativePath),
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $repoRoot);
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);

        self::assertSame(
            0,
            $exitCode,
            sprintf(
                "`phel test %s` failed.\nSTDOUT:\n%s\nSTDERR:\n%s",
                $relativePath,
                $stdout,
                $stderr,
            ),
        );
        self::assertStringContainsString('Failed: 0', $stdout);
        self::assertStringContainsString('Error: 0', $stdout);
    }
}
