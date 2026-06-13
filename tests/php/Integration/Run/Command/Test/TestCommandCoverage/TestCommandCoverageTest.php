<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Test\TestCommandCoverage;

use Override;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function dirname;
use function escapeshellarg;
use function exec;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function mkdir;
use function random_bytes;
use function simplexml_load_string;
use function sprintf;
use function str_contains;
use function sys_get_temp_dir;

/**
 * End-to-end coverage of `phel test --coverage`. The actual collection needs a
 * line-coverage extension (pcov/xdebug); when neither is available in the
 * subprocess the test skips, so it exercises the real path on coverage-enabled
 * runners without failing elsewhere.
 */
final class TestCommandCoverageTest extends TestCase
{
    private string $projectDir;

    private string $repoRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->repoRoot = dirname(__DIR__, 7);
        $this->projectDir = sys_get_temp_dir() . '/phel-coverage-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/src', 0o755, true);
        mkdir($this->projectDir . '/tests', 0o755, true);
        mkdir($this->projectDir . '/vendor', 0o755, true);

        file_put_contents(
            $this->projectDir . '/vendor/autoload.php',
            sprintf("<?php return require '%s/vendor/autoload.php';\n", $this->repoRoot),
        );
        file_put_contents(
            $this->projectDir . '/phel-config.php',
            "<?php\nreturn new \\Phel\\Config\\PhelConfig()\n"
            . "    ->withSrcDirs(['src'])->withTestDirs(['tests'])->withVendorDir('');\n",
        );
        file_put_contents(
            $this->projectDir . '/src/calc.phel',
            "(ns app.calc)\n\n(defn add [a b]\n  (+ a b))\n\n(defn unused-fn [x]\n  (* x 100))\n",
        );
        file_put_contents(
            $this->projectDir . '/tests/calc_test.phel',
            "(ns app.calc-test\n  (:require phel\\test :refer [deftest is])\n  (:require app.calc))\n"
            . "(deftest add-works\n  (is (= 3 (app.calc/add 1 2))))\n",
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->projectDir));
    }

    public function test_text_coverage_reports_per_file_percentages(): void
    {
        [, $output] = $this->runPhelTest(['--coverage']);
        $this->skipIfNoDriver($output);

        self::assertStringContainsString('Coverage', $output);
        self::assertStringContainsString('calc.phel', $output);
        self::assertMatchesRegularExpression('/\d+\.\d%/', $output);
        self::assertStringContainsString('Total', $output);
    }

    public function test_clover_coverage_writes_valid_xml(): void
    {
        $cloverPath = $this->projectDir . '/clover.xml';
        [, $output] = $this->runPhelTest(['--coverage=clover', '--coverage-output=' . $cloverPath]);
        $this->skipIfNoDriver($output);

        self::assertFileExists($cloverPath);
        $xml = simplexml_load_string((string) file_get_contents($cloverPath));
        self::assertNotFalse($xml, 'clover output is well-formed XML');
        self::assertStringContainsString('calc.phel', (string) file_get_contents($cloverPath));
    }

    private function skipIfNoDriver(string $output): void
    {
        if (str_contains($output, 'requires the pcov or xdebug extension')) {
            self::markTestSkipped('No line-coverage extension (pcov/xdebug) available in the subprocess.');
        }
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{0: int, 1: string}
     */
    private function runPhelTest(array $arguments): array
    {
        $args = '';
        foreach ($arguments as $argument) {
            $args .= ' ' . escapeshellarg($argument);
        }

        $cmd = 'cd ' . escapeshellarg($this->projectDir)
            . ' && php -d memory_limit=256M ' . escapeshellarg($this->repoRoot . '/bin/phel')
            . ' test' . $args . ' 2>&1';

        exec($cmd, $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }
}
