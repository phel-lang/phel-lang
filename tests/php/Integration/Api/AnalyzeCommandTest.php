<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Phel;
use Phel\Api\Infrastructure\Command\AnalyzeCommand;
use Phel\Api\Infrastructure\Command\ApiDaemonCommand;
use Phel\Api\Infrastructure\Command\IndexCommand;
use Phel\Compiler\Infrastructure\GlobalEnvironmentSingleton;
use Phel\Lang\Symbol;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

final class AnalyzeCommandTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_analyze_command_prints_json_diagnostics(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new AnalyzeCommand());
        $exit = $tester->execute(['paths' => [__DIR__ . '/Fixtures/arity_mismatch.phel']]);
        self::assertSame(1, $exit, 'an error diagnostic fails the run');

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertNotEmpty($decoded);
        self::assertArrayHasKey('code', $decoded[0]);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_analyze_command_reports_an_absolute_uri_and_one_based_columns(): void
    {
        $this->bootstrap();
        chdir(__DIR__);

        $tester = new CommandTester(new AnalyzeCommand());
        $tester->execute(['paths' => ['Fixtures/arity_mismatch.phel']]);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertSame(
            [realpath(__DIR__ . '/Fixtures/arity_mismatch.phel'), 3, 1, 3, 16],
            [$decoded[0]['uri'], $decoded[0]['startLine'], $decoded[0]['startCol'], $decoded[0]['endLine'], $decoded[0]['endCol']],
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_analyze_command_resolves_core_macros(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new AnalyzeCommand());
        $exit = $tester->execute(['paths' => [__DIR__ . '/Fixtures/uses_core_macro.phel']]);
        self::assertSame(0, $exit);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertSame(
            [],
            $decoded,
            'Expected no diagnostics when the analyzed file only references phel\\core macros',
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_analyze_command_resolves_aliased_symbols_from_project_namespace(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new AnalyzeCommand());
        $exit = $tester->execute(['paths' => [__DIR__ . '/Fixtures/bar.phel']]);
        self::assertSame(0, $exit);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertSame(
            [],
            $decoded,
            'Expected no diagnostics for aliased symbols pointing to a required project namespace',
        );
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_analyze_command_fails_when_file_missing(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new AnalyzeCommand());
        $exit = $tester->execute(['paths' => ['/nonexistent/file.phel']]);

        self::assertSame(2, $exit);
    }

    public function test_analyze_command_fails_on_an_unreadable_subdirectory(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('root reads any directory');
        }

        $this->bootstrap();
        $dir = sys_get_temp_dir() . '/phel-analyze-unreadable-child-' . uniqid();
        mkdir($dir . '/locked', 0o777, true);
        file_put_contents($dir . '/main.phel', '(ns app.main)');
        chmod($dir . '/locked', 0o000);

        try {
            $tester = new CommandTester(new AnalyzeCommand());
            $exit = $tester->execute(['paths' => [$dir]]);
        } finally {
            chmod($dir . '/locked', 0o755);
            rmdir($dir . '/locked');
            unlink($dir . '/main.phel');
            rmdir($dir);
        }

        self::assertSame(2, $exit);
        self::assertStringContainsString('Unable to read directory', $tester->getDisplay());
    }

    public function test_analyze_command_fails_on_an_unreadable_directory(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('root reads any directory');
        }

        $this->bootstrap();
        $dir = sys_get_temp_dir() . '/phel-analyze-unreadable-' . uniqid();
        mkdir($dir);
        chmod($dir, 0o000);

        try {
            $tester = new CommandTester(new AnalyzeCommand());
            $exit = $tester->execute(['paths' => [$dir]]);
        } finally {
            chmod($dir, 0o755);
            rmdir($dir);
        }

        self::assertSame(2, $exit);
        self::assertStringContainsString('Unable to read directory', $tester->getDisplay());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_index_command_prints_summary(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new IndexCommand());
        $exit = $tester->execute(['dirs' => [__DIR__ . '/Fixtures']]);
        self::assertSame(0, $exit);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('definitions', $decoded);
        self::assertGreaterThan(0, $decoded['definitions']);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_api_daemon_command_is_registered(): void
    {
        $command = new ApiDaemonCommand();
        self::assertSame('api-daemon', $command->getName());
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
