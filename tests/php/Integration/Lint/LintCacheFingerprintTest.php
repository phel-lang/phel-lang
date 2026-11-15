<?php

declare(strict_types=1);

namespace PhelTest\Integration\Lint;

use Phel;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use Phel\Lint\Infrastructure\Command\LintCommand;
use PhelTest\Support\RemoveDirTrait;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

use function array_map;
use function chdir;
use function file_get_contents;
use function file_put_contents;
use function getcwd;
use function is_dir;
use function json_decode;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class LintCacheFingerprintTest extends TestCase
{
    use RemoveDirTrait;

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
            "(ns fixtures.unused-binding)\n\n(defn demo []\n  (let [x 1\n        y 2]\n    y))\n",
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

    public function test_fixing_a_dependency_clears_cached_consumer_errors(): void
    {
        mkdir($this->projectRoot . '/src');
        mkdir($this->projectRoot . '/tests');
        file_put_contents($this->projectRoot . '/phel-config.php', <<<'PHP'
<?php
return \Phel\Config\PhelConfig::forProject()
    ->withSrcDirs(['src'])
    ->withTestDirs(['tests']);
PHP);
        $dependency = $this->projectRoot . '/src/dep.phel';
        file_put_contents($dependency, <<<'PHEL'
(ns repro.dep
  (:require phel.string :as conflict)
  (:require phel.json :as conflict))
(defn value [] 3)
PHEL);
        file_put_contents($this->projectRoot . '/tests/consumer.phel', <<<'PHEL'
(ns repro.consumer
  (:require phel.bench :refer [defbench])
  (:require repro.dep :refer [value]))
(defbench sample (value))
PHEL);

        $first = $this->runProjectLint();
        self::assertSame(1, $first->getExitCode(), $first->getErrorOutput() . $first->getOutput());
        self::assertStringContainsString('PHEL004', $first->getOutput());

        file_put_contents($dependency, str_replace('phel.json :as conflict', 'phel.json :as json', (string) file_get_contents($dependency)));

        $second = $this->runProjectLint();
        self::assertSame(0, $second->getExitCode(), $second->getErrorOutput() . $second->getOutput());
        self::assertSame($this->runProjectLint(true)->getOutput(), $second->getOutput());
        self::assertSame($second->getOutput(), $this->runProjectLint()->getOutput());
    }

    public function test_a_changed_function_signature_invalidates_a_clean_consumer(): void
    {
        mkdir($this->projectRoot . '/src');
        file_put_contents($this->projectRoot . '/phel-config.php', <<<'PHP'
<?php
return \Phel\Config\PhelConfig::forProject()->withSrcDirs(['src']);
PHP);
        $dependency = $this->projectRoot . '/src/dep.phel';
        file_put_contents($dependency, "(ns repro.dep)\n(defn value [] 3)\n");
        file_put_contents($this->projectRoot . '/src/consumer.phel', "(ns repro.consumer (:require repro.dep :refer [value]))\n(defn result [] (value))\n");

        $first = $this->runProjectLint();
        self::assertSame(0, $first->getExitCode(), $first->getErrorOutput() . $first->getOutput());

        $index = json_decode((string) file_get_contents($this->projectRoot . '/.phel/lint-cache/index.json'), true);
        self::assertIsArray($index);
        self::assertArrayHasKey((string) realpath($this->projectRoot . '/src/consumer.phel'), $index);

        file_put_contents($dependency, "(ns repro.dep)\n(defn value [x] x)\n");

        $second = $this->runProjectLint();
        self::assertSame(1, $second->getExitCode(), $second->getErrorOutput() . $second->getOutput());
        self::assertStringContainsString('phel/arity-mismatch', $second->getOutput());
        self::assertSame($this->runProjectLint(true)->getOutput(), $second->getOutput());
    }

    public function test_a_sibling_dependency_outside_configured_directories_invalidates_its_consumer(): void
    {
        mkdir($this->projectRoot . '/src');
        mkdir($this->projectRoot . '/external');
        file_put_contents($this->projectRoot . '/phel-config.php', <<<'PHP'
<?php
return \Phel\Config\PhelConfig::forProject()->withSrcDirs(['src']);
PHP);
        $dependency = $this->projectRoot . '/external/dep.phel';
        $consumer = $this->projectRoot . '/external/consumer.phel';
        file_put_contents($dependency, "(ns repro.dep)\n(defn value [] 3)\n");
        file_put_contents($consumer, "(ns repro.consumer (:require repro.dep :refer [value]))\n(defn result [] (value))\n");

        $run = function () use ($consumer): Process {
            $process = new Process([PHP_BINARY, __DIR__ . '/../../../../bin/phel', 'lint', $consumer, '--format=json'], $this->projectRoot);
            $process->run();

            return $process;
        };
        $first = $run();
        self::assertSame(0, $first->getExitCode(), $first->getErrorOutput() . $first->getOutput());
        file_put_contents($dependency, "(ns repro.dep)\n(defn value [x] x)\n");
        $second = $run();
        self::assertSame(1, $second->getExitCode(), $second->getErrorOutput() . $second->getOutput());
        self::assertStringContainsString('phel/arity-mismatch', $second->getOutput());
    }

    public function test_changing_a_loaded_file_refreshes_its_callers_diagnostics(): void
    {
        mkdir($this->projectRoot . '/src');
        file_put_contents($this->projectRoot . '/phel-config.php', <<<'PHP'
<?php
return \Phel\Config\PhelConfig::forProject()->withSrcDirs(['src']);
PHP);
        $helper = $this->projectRoot . '/src/helper.phel';
        file_put_contents($helper, "(in-ns repro.app)\n(defn helper [] 3)\n");
        file_put_contents($this->projectRoot . '/src/bridge.phel', "(in-ns repro.app)\n(load \"helper\")\n");
        file_put_contents($this->projectRoot . '/src/app.phel', "(ns repro.app)\n(load \"bridge\")\n(defn result [] (helper))\n");

        $first = $this->runProjectLint();
        self::assertSame(0, $first->getExitCode(), $first->getErrorOutput() . $first->getOutput());
        file_put_contents($helper, "(in-ns repro.app)\n(defn helper [x] x)\n");
        $second = $this->runProjectLint();
        self::assertSame(1, $second->getExitCode(), $second->getErrorOutput() . $second->getOutput());
        self::assertStringContainsString('phel/arity-mismatch', $second->getOutput());
        self::assertSame($this->runProjectLint(true)->getOutput(), $second->getOutput());
    }

    private function runProjectLint(bool $noCache = false): Process
    {
        $args = [PHP_BINARY, __DIR__ . '/../../../../bin/phel', 'lint', 'src', '--format=json'];
        if (is_dir($this->projectRoot . '/tests')) {
            $args[] = 'tests';
        }

        if ($noCache) {
            $args[] = '--no-cache';
        }

        $process = new Process($args, $this->projectRoot);
        $process->run();

        return $process;
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

}
