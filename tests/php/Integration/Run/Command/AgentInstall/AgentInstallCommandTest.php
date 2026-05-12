<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\AgentInstall;

use Phel\Run\Infrastructure\Command\AgentInstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class AgentInstallCommandTest extends TestCase
{
    private string $testDir;

    private string $originalCwd;

    protected function setUp(): void
    {
        $this->originalCwd = (string) getcwd();
        $this->testDir = sys_get_temp_dir() . '/phel-agent-install-test-' . uniqid();
        mkdir($this->testDir, 0o755, true);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->testDir);
    }

    public function test_install_all_creates_every_skill_target(): void
    {
        $result = $this->install(['--all' => true]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertFileExists($this->testDir . '/.claude/skills/phel-lang/SKILL.md');
        self::assertFileExists($this->testDir . '/.cursor/rules/phel.mdc');
        self::assertFileExists($this->testDir . '/AGENTS.md');
        self::assertFileExists($this->testDir . '/GEMINI.md');
        self::assertFileExists($this->testDir . '/.github/copilot-instructions.md');
        self::assertFileExists($this->testDir . '/CONVENTIONS.md');
    }

    public function test_install_single_platform_only_creates_that_target(): void
    {
        $this->install(['platform' => 'claude']);

        self::assertFileExists($this->testDir . '/.claude/skills/phel-lang/SKILL.md');
        self::assertFileDoesNotExist($this->testDir . '/AGENTS.md');
        self::assertFileDoesNotExist($this->testDir . '/GEMINI.md');
        self::assertFileDoesNotExist($this->testDir . '/CONVENTIONS.md');
    }

    public function test_missing_platform_and_no_all_returns_invalid(): void
    {
        $output = new BufferedOutput();
        $command = new AgentInstallCommand();

        $result = $command->run(new ArrayInput([]), $output);

        self::assertSame(Command::INVALID, $result);
        self::assertStringContainsString('Provide a platform or use --all', $output->fetch());
    }

    public function test_unknown_platform_returns_invalid(): void
    {
        $output = new BufferedOutput();
        $command = new AgentInstallCommand();

        $result = $command->run(new ArrayInput(['platform' => 'borg']), $output);

        self::assertSame(Command::INVALID, $result);
        self::assertStringContainsString('Unknown platform: borg', $output->fetch());
    }

    public function test_dry_run_does_not_create_files(): void
    {
        $output = new BufferedOutput();
        $result = $this->install(['--all' => true, '--dry-run' => true], $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertFileDoesNotExist($this->testDir . '/AGENTS.md');
        self::assertFileDoesNotExist($this->testDir . '/CONVENTIONS.md');
        self::assertStringContainsString('[dry-run]', $output->fetch());
    }

    public function test_reinstall_backs_up_existing_file(): void
    {
        $this->install(['platform' => 'codex']);
        file_put_contents($this->testDir . '/AGENTS.md', 'user-edited content');

        $this->install(['platform' => 'codex']);

        self::assertFileExists($this->testDir . '/AGENTS.md.pre-phel.bak');
        self::assertSame('user-edited content', file_get_contents($this->testDir . '/AGENTS.md.pre-phel.bak'));
    }

    public function test_force_skips_backup(): void
    {
        $this->install(['platform' => 'codex']);

        $this->install(['platform' => 'codex', '--force' => true]);

        self::assertFileDoesNotExist($this->testDir . '/AGENTS.md.pre-phel.bak');
    }

    public function test_installed_file_carries_version_stamp(): void
    {
        $this->install(['platform' => 'aider']);

        $content = (string) file_get_contents($this->testDir . '/CONVENTIONS.md');
        self::assertMatchesRegularExpression('/<!-- phel-agents v\d+\.\d+\.\d+ -->/', $content);
    }

    public function test_reinstall_does_not_duplicate_version_stamp(): void
    {
        $this->install(['platform' => 'aider']);
        $this->install(['platform' => 'aider', '--force' => true]);

        $content = (string) file_get_contents($this->testDir . '/CONVENTIONS.md');
        self::assertSame(
            1,
            preg_match_all('/<!-- phel-agents v/', $content),
            'Version stamp must appear exactly once after reinstall',
        );
    }

    public function test_with_docs_copies_agents_tree(): void
    {
        $this->install(['platform' => 'claude', '--with-docs' => true]);

        self::assertDirectoryExists($this->testDir . '/.agents');
        self::assertFileExists($this->testDir . '/.agents/RULES.md');
        self::assertFileExists($this->testDir . '/.agents/index.md');
        self::assertFileExists($this->testDir . '/.agents/quick-syntax.md');
        self::assertDirectoryExists($this->testDir . '/.agents/tasks');
        self::assertDirectoryExists($this->testDir . '/.agents/examples');
    }

    public function test_with_docs_skips_when_agents_dir_already_exists(): void
    {
        mkdir($this->testDir . '/.agents', 0o755, true);
        file_put_contents($this->testDir . '/.agents/marker.txt', 'keep me');

        $output = new BufferedOutput();
        $this->install(['platform' => 'claude', '--with-docs' => true], $output);

        self::assertFileExists($this->testDir . '/.agents/marker.txt');
        self::assertStringContainsString('.agents/ already exists', $output->fetch());
    }

    public function test_claude_skill_references_quick_syntax(): void
    {
        $this->install(['platform' => 'claude']);

        $content = (string) file_get_contents($this->testDir . '/.claude/skills/phel-lang/SKILL.md');
        self::assertStringContainsString('quick-syntax.md', $content);
    }

    public function test_check_reports_not_installed_for_fresh_project(): void
    {
        $output = new BufferedOutput();
        $result = $this->install(['--check' => true], $output);

        self::assertSame(Command::SUCCESS, $result);
        $rendered = $output->fetch();
        self::assertStringContainsString('claude   not installed', $rendered);
        self::assertStringContainsString('cursor   not installed', $rendered);
    }

    public function test_check_reports_current_for_freshly_installed(): void
    {
        $this->install(['platform' => 'aider']);

        $output = new BufferedOutput();
        $result = $this->install(['--check' => true], $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertMatchesRegularExpression('/aider\s+v\d+\.\d+\.\d+/', $output->fetch());
    }

    public function test_check_returns_failure_when_stamp_is_stale(): void
    {
        $this->install(['platform' => 'aider']);
        $path = $this->testDir . '/CONVENTIONS.md';
        $original = (string) file_get_contents($path);
        file_put_contents($path, preg_replace('/<!-- phel-agents v[^>]*-->/', '<!-- phel-agents v0.0.1 -->', $original));

        $output = new BufferedOutput();
        $result = $this->install(['--check' => true], $output);

        self::assertSame(Command::FAILURE, $result);
        self::assertStringContainsString('v0.0.1', $output->fetch());
    }

    public function test_check_reports_unstamped_when_file_has_no_stamp(): void
    {
        mkdir($this->testDir . '/.github', 0o755, true);
        file_put_contents($this->testDir . '/.github/copilot-instructions.md', "# manual content\nno stamp here\n");

        $output = new BufferedOutput();
        $result = $this->install(['--check' => true], $output);

        self::assertSame(Command::FAILURE, $result);
        self::assertStringContainsString('copilot  file exists but has no version stamp', $output->fetch());
    }

    /**
     * @param array<string, mixed> $args
     */
    private function install(array $args, ?BufferedOutput $output = null): int
    {
        $command = new AgentInstallCommand();
        return $command->run(new ArrayInput($args), $output ?? new BufferedOutput());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
