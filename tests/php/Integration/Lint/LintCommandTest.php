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

use function json_decode;

final class LintCommandTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_emits_json_diagnostics_for_unused_binding_fixture(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/unused_binding.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        self::assertContains($exit, [0, 1]);
        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload);

        $codes = array_map(static fn(array $d): string => $d['code'], $payload);
        self::assertContains('phel/unused-binding', $codes);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_returns_zero_on_clean_fixture(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/clean.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        self::assertSame(0, $exit);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_fails_with_invocation_error_on_unknown_format(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/clean.phel'],
            '--format' => 'bogus',
        ]);

        self::assertSame(LintCommand::EXIT_INVOCATION_ERROR, $exit);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_fails_with_invocation_error_when_no_readable_paths(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => ['/nonexistent/path/does/not/exist.phel'],
        ]);

        self::assertSame(LintCommand::EXIT_INVOCATION_ERROR, $exit);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_it_suppresses_unresolved_symbol_for_known_require_alias(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $exit = $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/require_alias.phel'],
            '--format' => 'json',
            '--no-cache' => true,
        ]);

        $payload = json_decode(trim($tester->getDisplay()), true);
        self::assertIsArray($payload);

        $codes = array_map(static fn(array $d): string => $d['code'], $payload);
        self::assertNotContains(
            'phel/unresolved-symbol',
            $codes,
            'Alias-qualified call via (:require :as) must not be flagged as unresolved',
        );
        self::assertSame(0, $exit);
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_github_format_emits_annotation_commands(): void
    {
        $this->bootstrap();

        $tester = new CommandTester(new LintCommand());
        $tester->execute([
            'paths' => [__DIR__ . '/Fixtures/unused_binding.phel'],
            '--format' => 'github',
            '--no-cache' => true,
        ]);

        $out = $tester->getDisplay();
        self::assertStringContainsString('::', $out);
        self::assertMatchesRegularExpression('/^::(error|warning|notice) /m', $out);
    }

    /**
     * Regression test for https://github.com/phel-lang/phel-lang/issues/1541:
     * running `phel lint` with no paths must not re-analyze phel's own bundled
     * stdlib files (reachable because `CommandConfig` prepends phel's internal
     * src dir for runtime namespace resolution). Re-analyzing them caused a
     * `DuplicateDefinitionException` for symbols like `phel\walk/walk`.
     */
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function test_default_paths_exclude_phel_internal_stdlib(): void
    {
        $originalCwd = getcwd();
        $projectRoot = sys_get_temp_dir() . '/phel-lint-1541-' . uniqid('', true);
        mkdir($projectRoot . '/src', 0o777, true);
        file_put_contents($projectRoot . '/src/clean.phel', "(ns consumer\\clean)\n(defn f [] :ok)\n");

        try {
            chdir($projectRoot);
            Phel::bootstrap($projectRoot);
            Phel::clear();
            Symbol::resetGen();
            GlobalEnvironmentSingleton::initializeNew();

            $tester = new CommandTester(new LintCommand());
            $exit = $tester->execute([
                '--format' => 'json',
                '--no-cache' => true,
            ]);

            self::assertNotSame(
                LintCommand::EXIT_INVOCATION_ERROR,
                $exit,
                'Lint with no paths must not abort from re-binding bundled stdlib symbols. '
                . 'Output: ' . $tester->getDisplay(),
            );
            self::assertStringNotContainsString('already bound', $tester->getDisplay());
        } finally {
            if ($originalCwd !== false) {
                chdir($originalCwd);
            }

            @unlink($projectRoot . '/src/clean.phel');
            if (is_dir($projectRoot . '/src')) {
                $leftovers = scandir($projectRoot . '/src') ?: [];
                foreach ($leftovers as $entry) {
                    if ($entry === '.') {
                        continue;
                    }

                    if ($entry === '..') {
                        continue;
                    }

                    @unlink($projectRoot . '/src/' . $entry);
                }

                @rmdir($projectRoot . '/src');
            }

            @rmdir($projectRoot);
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
