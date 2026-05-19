<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Infrastructure\Command\LintCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function array_map;
use function chdir;
use function file_put_contents;
use function getcwd;
use function is_dir;
use function json_decode;
use function mkdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * Verifies that changing phel-lint.phel config (severities or exclude
 * patterns) invalidates the cache so a second run without --no-cache
 * reflects the updated config.
 */
final class LintCacheFingerprintTest extends TestCase
{
    private string $projectRoot;

    private string $configPath;

    private string|false $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = getcwd();
        $this->projectRoot = sys_get_temp_dir() . '/phel-lint-fp-' . uniqid('', true);
        $this->configPath = $this->projectRoot . '/phel-lint.phel';
        mkdir($this->projectRoot, 0o777, true);

        file_put_contents(
            $this->projectRoot . '/unused_binding.phel',
            "(ns fixtures\\unused-binding)\n\n(defn demo []\n  (let [x 1\n        y 2]\n    y))\n",
        );
    }

    protected function tearDown(): void
    {
        if ($this->originalCwd !== false) {
            chdir($this->originalCwd);
        }

        $this->removeDir($this->projectRoot);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_is_invalidated_when_severity_changes_to_off(): void
    {
        chdir($this->projectRoot);
        $this->bootstrap();

        $fixture = $this->projectRoot . '/unused_binding.phel';

        // First run: default config — unused-binding should produce a warning
        $firstResult = $this->runLint($fixture);
        $codes = array_map(static fn(array $d): string => $d['code'], $firstResult);
        self::assertContains(
            'phel/unused-binding',
            $codes,
            'First run must report unused-binding diagnostic',
        );

        // Change config: disable unused-binding entirely
        file_put_contents($this->configPath, "{:rules {:phel/unused-binding :off}}\n");

        // Second run WITHOUT --no-cache — must reflect the new config
        $secondResult = $this->runLint($fixture, $this->configPath);
        $codes = array_map(static fn(array $d): string => $d['code'], $secondResult);
        self::assertNotContains(
            'phel/unused-binding',
            $codes,
            'Second run must not report unused-binding after config sets it to :off — cache must have been invalidated',
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_cache_is_invalidated_when_exclude_pattern_added(): void
    {
        chdir($this->projectRoot);
        $this->bootstrap();

        $fixture = $this->projectRoot . '/unused_binding.phel';

        // First run: no exclusions — expect the warning
        $firstResult = $this->runLint($fixture);
        $codes = array_map(static fn(array $d): string => $d['code'], $firstResult);
        self::assertContains(
            'phel/unused-binding',
            $codes,
            'First run must report unused-binding diagnostic',
        );

        // Add an :exclude pattern that matches the fixture file
        file_put_contents(
            $this->configPath,
            "{:exclude {:phel/unused-binding [\"*/unused_binding.phel\"]}}\n",
        );

        // Second run WITHOUT --no-cache — must respect the exclude
        $secondResult = $this->runLint($fixture, $this->configPath);
        $codes = array_map(static fn(array $d): string => $d['code'], $secondResult);
        self::assertNotContains(
            'phel/unused-binding',
            $codes,
            'Second run must suppress unused-binding after exclude pattern added — cache must have been invalidated',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runLint(string $fixture, ?string $configPath = null): array
    {
        $args = [
            'paths' => [$fixture],
            '--format' => 'json',
        ];
        if ($configPath !== null) {
            $args['--config'] = $configPath;
        }

        $tester = new CommandTester(new LintCommand());
        $tester->execute($args);

        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload, 'Lint output must be a JSON array. Got: ' . $tester->getDisplay());

        return $payload;
    }

    private function bootstrap(): void
    {
        Phel::bootstrap($this->projectRoot);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
