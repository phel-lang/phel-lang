<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\BundledNamespaceFqnRegression;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function exec;
use function implode;

final class BundledNamespaceFqnRegressionTest extends TestCase
{
    public function test_phel_test_resolves_bundled_namespace_fqns_without_explicit_require(): void
    {
        $projectRoot = __DIR__ . '/../../../../../../..';
        $bin         = $projectRoot . '/bin/phel';
        $fixture     = $projectRoot . '/tests/phel/test/phel-async-fqn-regression.phel';

        $cmd = 'cd ' . escapeshellarg($projectRoot)
            . ' && php -d memory_limit=256M ' . escapeshellarg($bin)
            . ' test ' . escapeshellarg($fixture) . ' 2>&1';

        exec($cmd, $output, $exitCode);
        $combined = implode("\n", $output);

        self::assertSame(0, $exitCode, 'phel test failed:' . PHP_EOL . $combined);
        self::assertMatchesRegularExpression('/Passed:\s*3/', $combined, $combined);
        self::assertMatchesRegularExpression('/Failed:\s*0/', $combined, $combined);
        self::assertMatchesRegularExpression('/Error:\s*0/', $combined, $combined);
        self::assertMatchesRegularExpression('/Total:\s*3/', $combined, $combined);
    }
}
