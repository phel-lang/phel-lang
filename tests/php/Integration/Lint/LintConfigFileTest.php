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

use function file_put_contents;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * End-to-end check that a `.phel-lint.phel` config opt-out silences
 * a rule that would otherwise report diagnostics on the fixture.
 */
final class LintConfigFileTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_config_file_can_disable_a_rule(): void
    {
        $this->bootstrap();

        $configPath = tempnam(sys_get_temp_dir(), 'lint-cfg-');
        self::assertIsString($configPath);
        file_put_contents(
            $configPath,
            "{:rules {:phel/unused-binding :off}}\n",
        );

        try {
            $tester = new CommandTester(new LintCommand());
            $tester->execute([
                'paths' => [__DIR__ . '/Fixtures/unused_binding.phel'],
                '--format' => 'json',
                '--config' => $configPath,
                '--no-cache' => true,
            ]);

            $payload = json_decode(trim($tester->getDisplay()), true);
            self::assertIsArray($payload);

            $codes = array_map(static fn(array $d): string => $d['code'], $payload);
            self::assertNotContains('phel/unused-binding', $codes);
        } finally {
            @unlink($configPath);
        }
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_malformed_config_file_fails_the_run(): void
    {
        $this->bootstrap();

        $configPath = tempnam(sys_get_temp_dir(), 'lint-cfg-');
        self::assertIsString($configPath);
        file_put_contents($configPath, "{:rules {:phel/unused-binding :off}\n");

        try {
            $tester = new CommandTester(new LintCommand());
            $exitCode = $tester->execute([
                'paths' => [__DIR__ . '/Fixtures/unused_binding.phel'],
                '--config' => $configPath,
                '--no-cache' => true,
            ]);

            self::assertSame(LintCommand::EXIT_INVOCATION_ERROR, $exitCode);
            self::assertStringContainsString('Cannot parse lint config file', $tester->getDisplay());
        } finally {
            @unlink($configPath);
        }
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_config_file_that_is_not_a_map_fails_the_run(): void
    {
        $this->bootstrap();

        $configPath = tempnam(sys_get_temp_dir(), 'lint-cfg-');
        self::assertIsString($configPath);
        file_put_contents($configPath, "[:rules]\n");

        try {
            $tester = new CommandTester(new LintCommand());
            $exitCode = $tester->execute([
                'paths' => [__DIR__ . '/Fixtures/unused_binding.phel'],
                '--config' => $configPath,
                '--no-cache' => true,
            ]);

            self::assertSame(LintCommand::EXIT_INVOCATION_ERROR, $exitCode);
            self::assertStringContainsString('must contain a single map', $tester->getDisplay());
        } finally {
            @unlink($configPath);
        }
    }

    private function bootstrap(): void
    {
        Phel::bootstrap(__DIR__);
        Phel::clear();
        Symbol::resetGen();
        GlobalEnvironmentSingleton::initializeNew();
    }
}
