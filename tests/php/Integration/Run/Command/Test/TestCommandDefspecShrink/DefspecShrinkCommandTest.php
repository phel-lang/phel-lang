<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandDefspecShrink;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function exec;
use function implode;

/**
 * Boots `./bin/phel test tests/phel/test/defspec-shrink.phel` and
 * asserts the fixture suite runs cleanly. Covers the
 * rose-tree/shrinker/defspec pipeline end-to-end through the public
 * CLI entry point.
 */
final class DefspecShrinkCommandTest extends TestCase
{
    public function test_defspec_shrinking_suite_runs_through_test_command(): void
    {
        $projectRoot = __DIR__ . '/../../../../../../..';
        $bin         = $projectRoot . '/bin/phel';
        $fixture     = $projectRoot . '/tests/phel/test/defspec-shrink.phel';

        $cmd = 'cd ' . escapeshellarg($projectRoot)
            . ' && php -d memory_limit=256M ' . escapeshellarg($bin)
            . ' test ' . escapeshellarg($fixture) . ' 2>&1';

        exec($cmd, $output, $exitCode);
        $combined = implode("\n", $output);

        self::assertSame(0, $exitCode, 'phel test failed:
' . $combined);
        self::assertMatchesRegularExpression('/Failed:\s*0/', $combined, $combined);
        self::assertMatchesRegularExpression('/Error:\s*0/', $combined, $combined);
        self::assertMatchesRegularExpression('/Total:\s*(?!0\b)\d+/', $combined, $combined);
    }

    public function test_rose_tree_tests_run_through_test_command(): void
    {
        $projectRoot = __DIR__ . '/../../../../../../..';
        $bin         = $projectRoot . '/bin/phel';
        $fixture     = $projectRoot . '/tests/phel/test/rose.phel';

        $cmd = 'cd ' . escapeshellarg($projectRoot)
            . ' && php -d memory_limit=256M ' . escapeshellarg($bin)
            . ' test ' . escapeshellarg($fixture) . ' 2>&1';

        exec($cmd, $output, $exitCode);
        $combined = implode("\n", $output);

        self::assertSame(0, $exitCode, 'phel test failed:
' . $combined);
        self::assertMatchesRegularExpression('/Failed:\s*0/', $combined, $combined);
        self::assertMatchesRegularExpression('/Error:\s*0/', $combined, $combined);
    }

    public function test_shrink_driver_tests_run_through_test_command(): void
    {
        $projectRoot = __DIR__ . '/../../../../../../..';
        $bin         = $projectRoot . '/bin/phel';
        $fixture     = $projectRoot . '/tests/phel/test/shrink.phel';

        $cmd = 'cd ' . escapeshellarg($projectRoot)
            . ' && php -d memory_limit=256M ' . escapeshellarg($bin)
            . ' test ' . escapeshellarg($fixture) . ' 2>&1';

        exec($cmd, $output, $exitCode);
        $combined = implode("\n", $output);

        self::assertSame(0, $exitCode, 'phel test failed:
' . $combined);
        self::assertMatchesRegularExpression('/Failed:\s*0/', $combined, $combined);
        self::assertMatchesRegularExpression('/Error:\s*0/', $combined, $combined);
    }
}
