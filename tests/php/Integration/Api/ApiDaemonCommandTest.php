<?php

declare(strict_types=1);

namespace PhelTest\Integration\Api;

use Gacela\Framework\Gacela;
use Phel;
use Phel\Api\ApiFacade;
use Phel\Api\Infrastructure\Command\ApiDaemonCommand;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Stdout carries only JSON-RPC, so a daemon that cannot start says why on stderr (#3525).
 */
final class ApiDaemonCommandTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_a_crash_is_reported_on_stderr_only(): void
    {
        Phel::bootstrap(__DIR__);
        Gacela::overrideExistingResolvedClass(ApiFacade::class, new class() {
            public function createApiDaemon(): never
            {
                throw new RuntimeException('daemon broke');
            }
        });

        $tester = new CommandTester(new ApiDaemonCommand());
        $exitCode = $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertSame(1, $exitCode);
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('daemon broke', $tester->getErrorOutput());
    }
}
