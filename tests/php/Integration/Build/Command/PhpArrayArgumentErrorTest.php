<?php

declare(strict_types=1);

namespace PhelTest\Integration\Build\Command;

use PhelTest\Support\Subprocess;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function sprintf;

final class PhpArrayArgumentErrorTest extends TestCase
{
    private BuildCommandWorkspace $workspace;

    protected function setUp(): void
    {
        $this->workspace = new BuildCommandWorkspace('array-argument-hint');
        $this->workspace->writeFile('phel-config.php', <<<'PHP'
<?php
return \Phel\Config\PhelConfig::forProject()
    ->withSrcDirs(['src'])
    ->withVendorDir('')
    ->withMainPhelNamespace('arrayhint')
    ->withMainPhpPath('out/main.php')
    ->withBuildDestDir('out');
PHP);
        $this->workspace->writeFile('scripts/entry.phel', "(ns arrayhint-entry (:require arrayhint :as a))\n(a/main)\n");
    }

    protected function tearDown(): void
    {
        $this->workspace->remove();
    }

    public static function collectionCalls(): iterable
    {
        yield 'map to function' => ['(php/array_keys %s)', '{"x" 1}', 'map', 1];
        yield 'vector to function' => ['(php/array_keys %s)', '[1 2]', 'vector', 2];
        yield 'map to method' => ['(.takeOptions (new Native) %s)', '{"x" 1}', 'map', 1];
        yield 'vector to method' => ['(.takeOptions (new Native) %s)', '[1 2]', 'vector', 2];
    }

    #[DataProvider('collectionCalls')]
    public function test_source_call_reports_the_phel_location_and_explicit_conversion(string $call, string $value, string $kind, int $count): void
    {
        $this->writeProvider(sprintf($call, $value));
        $failed = $this->phel(['run', 'scripts/entry.phel']);
        $this->assertArrayError($failed, $kind);

        $this->writeProvider(sprintf($call, '(to-array ' . $value . ')'));
        $success = $this->phel(['run', 'scripts/entry.phel']);
        self::assertSame(0, $success->exitCode, $success->stderr);
        self::assertSame($count . "\n", $success->stdout);
    }

    public function test_machine_profile_keeps_the_runtime_report_on_stderr(): void
    {
        $this->writeProvider('(.takeOptions (new Native) {"x" 1})');
        $result = $this->phel(['profile', '--format=json', 'scripts/entry.phel']);
        self::assertSame('', $result->stdout);
        $this->assertArrayError($result, 'map');
    }

    public static function builtCollections(): iterable
    {
        yield 'built map' => ['{"x" 1}', 'map', 1];
        yield 'built vector' => ['[1 2]', 'vector', 2];
    }

    #[DataProvider('builtCollections')]
    public function test_built_method_call_keeps_the_original_phel_location(string $value, string $kind, int $count): void
    {
        $this->writeProvider('(.takeOptions (new Native) ' . $value . ')');
        $this->build();
        $built = $this->runBuilt();
        self::assertSame('', $built->stdout);
        $this->assertArrayError($built, $kind);

        $this->writeProvider('(.takeOptions (new Native) (to-array ' . $value . '))');
        $this->build();
        $success = $this->runBuilt();
        self::assertSame(0, $success->exitCode, $success->stderr);
        self::assertSame($count . "\n", $success->stdout);
    }

    private function writeProvider(string $call): void
    {
        $this->workspace->writeFile('src/arrayhint.phel', "(ns arrayhint (:use PhelTest.Support.Fixtures.PhpInterop.ArrayArgumentFixture :as Native))\n\n(defn main []\n  (println (count " . $call . ")))\n");
    }

    private function assertArrayError(Subprocess $result, string $kind): void
    {
        self::assertSame(1, $result->exitCode, $result->stderr . $result->stdout);
        $report = $result->stderr . $result->stdout;
        self::assertStringContainsString('[PHEL402]', $report);
        self::assertStringContainsString('must be of type array', $report);
        self::assertStringContainsString('Phel\\Lang\\Collections\\', $report);
        self::assertMatchesRegularExpression('~^  at .*/(?:src|out)/arrayhint\.phel:4(?: |$)~m', $report);
        self::assertStringContainsString('PHP expects an array, but received a Phel ' . $kind, $report);
        self::assertStringContainsString('(to-array value)', $report);
        self::assertStringContainsString('(phel->php value)', $report);
    }

    private function phel(array $args): Subprocess
    {
        return Subprocess::run([PHP_BINARY, dirname(__DIR__, 5) . '/bin/phel', ...$args], $this->workspace->root());
    }

    private function build(): void
    {
        $result = $this->phel(['build']);
        self::assertSame(0, $result->exitCode, $result->stderr . $result->stdout);
    }

    private function runBuilt(): Subprocess
    {
        $runner = sprintf(
            <<<'PHP'
<?php
require %s;
\Phel::bootstrap(%s);
(new \Phel\Run\RunFacade())->loadPhelNamespaces();
require %s;
try {
    (\Phel::getDefinition('arrayhint', 'main'))();
} catch (\Throwable $error) {
    (new \Phel\Command\CommandFacade())->writeStackTrace((new \Symfony\Component\Console\Output\ConsoleOutput())->getErrorOutput(), $error, false);
    exit(1);
}
PHP,
            var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true),
            var_export($this->workspace->root(), true),
            var_export($this->workspace->path('out/arrayhint.php'), true),
        );
        $this->workspace->writeFile('run.php', $runner);

        return Subprocess::run([PHP_BINARY, $this->workspace->path('run.php')], $this->workspace->root());
    }
}
