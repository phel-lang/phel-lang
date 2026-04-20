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
        $exit = $tester->execute(['file' => __DIR__ . '/Fixtures/arity_mismatch.phel']);
        self::assertSame(0, $exit);

        $decoded = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertNotEmpty($decoded);
        self::assertArrayHasKey('code', $decoded[0]);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_analyze_command_fails_when_file_missing(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new AnalyzeCommand());
        $exit = $tester->execute(['file' => '/nonexistent/file.phel']);

        self::assertSame(1, $exit);
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
