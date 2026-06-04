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
        $this->install(['platform' => 'claude', '--no-docs' => true]);

        self::assertFileExists($this->testDir . '/.claude/skills/phel-lang/SKILL.md');
        self::assertFileDoesNotExist($this->testDir . '/AGENTS.md');
        self::assertFileDoesNotExist($this->testDir . '/GEMINI.md');
        self::assertFileDoesNotExist($this->testDir . '/CONVENTIONS.md');
    }

    public function test_missing_platform_returns_invalid(): void
    {
        $output = new BufferedOutput();
        $result = new AgentInstallCommand()->run(new ArrayInput([]), $output);

        self::assertSame(Command::INVALID, $result);
        self::assertStringContainsString('Provide a platform or use --all', $output->fetch());
    }

    public function test_unknown_platform_returns_invalid(): void
    {
        $output = new BufferedOutput();
        $result = new AgentInstallCommand()->run(new ArrayInput(['platform' => 'borg']), $output);

        self::assertSame(Command::INVALID, $result);
        $rendered = $output->fetch();
        self::assertStringContainsString('Unknown platform: borg', $rendered);
        self::assertStringContainsString('Available:', $rendered);
    }

    public function test_dry_run_does_not_create_files(): void
    {
        $output = new BufferedOutput();
        $result = $this->install(['--all' => true, '--dry-run' => true], $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertFileDoesNotExist($this->testDir . '/AGENTS.md');
        self::assertFileDoesNotExist($this->testDir . '/CONVENTIONS.md');
        $rendered = $output->fetch();
        self::assertStringContainsString('[dry-run]', $rendered);
        self::assertStringNotContainsString('Done.', $rendered);
    }

    public function test_reinstall_backs_up_existing_skill_file(): void
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

    public function test_install_copies_agents_tree_by_default_without_examples(): void
    {
        $this->install(['platform' => 'claude']);

        self::assertDirectoryExists($this->testDir . '/.agents');
        self::assertFileExists($this->testDir . '/.agents/RULES.md');
        self::assertFileExists($this->testDir . '/.agents/index.md');
        self::assertFileExists($this->testDir . '/.agents/quick-syntax.md');
        self::assertDirectoryExists($this->testDir . '/.agents/tasks');
        self::assertDirectoryDoesNotExist($this->testDir . '/.agents/examples');
    }

    public function test_install_with_examples_includes_sample_apps(): void
    {
        $this->install(['platform' => 'claude', '--with-examples' => true]);

        self::assertDirectoryExists($this->testDir . '/.agents/examples');
        self::assertFileExists($this->testDir . '/.agents/examples/README.md');
    }

    public function test_install_default_announces_skipped_examples(): void
    {
        $output = new BufferedOutput();
        $this->install(['platform' => 'claude'], $output);

        self::assertStringContainsString('Skipped examples/', $output->fetch());
    }

    public function test_no_docs_with_examples_emits_conflict_notice(): void
    {
        $output = new BufferedOutput();
        $this->install(['platform' => 'claude', '--no-docs' => true, '--with-examples' => true], $output);

        $rendered = $output->fetch();
        self::assertStringContainsString('--with-examples ignored', $rendered);
        self::assertDirectoryDoesNotExist($this->testDir . '/.agents');
    }

    public function test_no_docs_skips_agents_tree(): void
    {
        $this->install(['platform' => 'claude', '--no-docs' => true]);

        self::assertDirectoryDoesNotExist($this->testDir . '/.agents');
    }

    public function test_install_skips_docs_when_agents_dir_already_exists(): void
    {
        mkdir($this->testDir . '/.agents', 0o755, true);
        file_put_contents($this->testDir . '/.agents/marker.txt', 'keep me');

        $output = new BufferedOutput();
        $this->install(['platform' => 'claude'], $output);

        self::assertFileExists($this->testDir . '/.agents/marker.txt');
        self::assertStringContainsString('.agents/ already exists', $output->fetch());
    }

    public function test_force_overwrites_docs_tree(): void
    {
        mkdir($this->testDir . '/.agents', 0o755, true);

        $this->install(['platform' => 'claude', '--force' => true]);

        self::assertFileExists($this->testDir . '/.agents/RULES.md');
    }

    public function test_claude_skill_references_quick_syntax(): void
    {
        $this->install(['platform' => 'claude', '--no-docs' => true]);

        $content = (string) file_get_contents($this->testDir . '/.claude/skills/phel-lang/SKILL.md');
        self::assertStringContainsString('quick-syntax.md', $content);
    }

    public function test_uninstall_removes_skill_file_when_no_backup(): void
    {
        $this->install(['platform' => 'aider', '--force' => true, '--no-docs' => true]);
        self::assertFileExists($this->testDir . '/CONVENTIONS.md');

        $this->install(['platform' => 'aider', '--uninstall' => true, '--no-docs' => true]);

        self::assertFileDoesNotExist($this->testDir . '/CONVENTIONS.md');
    }

    public function test_uninstall_restores_backup_when_present(): void
    {
        file_put_contents($this->testDir . '/CONVENTIONS.md', 'user-original');
        $this->install(['platform' => 'aider', '--no-docs' => true]);

        $this->install(['platform' => 'aider', '--uninstall' => true, '--no-docs' => true]);

        self::assertSame('user-original', file_get_contents($this->testDir . '/CONVENTIONS.md'));
        self::assertFileDoesNotExist($this->testDir . '/CONVENTIONS.md.pre-phel.bak');
    }

    public function test_uninstall_all_removes_every_installed_file(): void
    {
        $this->install(['--all' => true, '--no-docs' => true]);

        $this->install(['--all' => true, '--uninstall' => true, '--no-docs' => true]);

        foreach (['AGENTS.md', 'GEMINI.md', 'CONVENTIONS.md'] as $f) {
            self::assertFileDoesNotExist($this->testDir . '/' . $f);
        }

        self::assertFileDoesNotExist($this->testDir . '/.claude/skills/phel-lang/SKILL.md');
        self::assertFileDoesNotExist($this->testDir . '/.cursor/rules/phel.mdc');
        self::assertFileDoesNotExist($this->testDir . '/.github/copilot-instructions.md');
    }

    public function test_uninstall_removes_agents_tree_by_default(): void
    {
        $this->install(['platform' => 'claude']);
        self::assertDirectoryExists($this->testDir . '/.agents');

        $this->install(['platform' => 'claude', '--uninstall' => true]);

        self::assertDirectoryDoesNotExist($this->testDir . '/.agents');
    }

    public function test_uninstall_no_docs_keeps_agents_tree(): void
    {
        $this->install(['platform' => 'claude']);
        self::assertDirectoryExists($this->testDir . '/.agents');

        $this->install(['platform' => 'claude', '--uninstall' => true, '--no-docs' => true]);

        self::assertDirectoryExists($this->testDir . '/.agents');
    }

    public function test_auto_installs_only_for_detected_agents(): void
    {
        mkdir($this->testDir . '/.claude', 0o755, true);
        mkdir($this->testDir . '/.cursor', 0o755, true);

        $output = new BufferedOutput();
        $result = $this->install(['--auto' => true], $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertFileExists($this->testDir . '/.claude/skills/phel-lang/SKILL.md');
        self::assertFileExists($this->testDir . '/.cursor/rules/phel.mdc');
        self::assertFileDoesNotExist($this->testDir . '/AGENTS.md');
        self::assertFileDoesNotExist($this->testDir . '/GEMINI.md');
        self::assertStringContainsString('Detected:', $output->fetch());
    }

    public function test_auto_with_no_detection_returns_invalid(): void
    {
        $output = new BufferedOutput();
        $result = $this->install(['--auto' => true], $output);

        self::assertSame(Command::INVALID, $result);
        self::assertStringContainsString('No agent traces detected', $output->fetch());
    }

    public function test_no_args_suggests_auto_when_agents_detected(): void
    {
        mkdir($this->testDir . '/.claude', 0o755, true);

        $output = new BufferedOutput();
        $result = $this->install([], $output);

        self::assertSame(Command::INVALID, $result);
        $rendered = $output->fetch();
        self::assertStringContainsString('Detected installed agents', $rendered);
        self::assertStringContainsString('agent-install --auto', $rendered);
    }

    public function test_dry_run_uninstall_previews_backup_restore_without_changing_files(): void
    {
        file_put_contents($this->testDir . '/CONVENTIONS.md', 'user-original');
        $this->install(['platform' => 'aider', '--no-docs' => true]);

        $output = new BufferedOutput();
        $this->install(['platform' => 'aider', '--uninstall' => true, '--no-docs' => true, '--dry-run' => true], $output);

        $rendered = $output->fetch();
        self::assertStringContainsString('[dry-run] remove', $rendered);
        self::assertStringContainsString('[dry-run] restore', $rendered);
        // Nothing actually changed: skill + backup both still present.
        self::assertFileExists($this->testDir . '/CONVENTIONS.md');
        self::assertFileExists($this->testDir . '/CONVENTIONS.md.pre-phel.bak');
    }

    public function test_uninstall_on_not_installed_platform_is_noop(): void
    {
        $output = new BufferedOutput();
        $result = $this->install(['platform' => 'aider', '--uninstall' => true, '--no-docs' => true], $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertStringContainsString('aider    not installed; skip', $output->fetch());
    }

    /**
     * @param array<string, mixed> $args
     */
    private function install(array $args, ?BufferedOutput $output = null): int
    {
        return new AgentInstallCommand()->run(new ArrayInput($args), $output ?? new BufferedOutput());
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
