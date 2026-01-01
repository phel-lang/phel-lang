<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Init;

use Phel\Run\Infrastructure\Command\InitCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class InitCommandTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/phel-init-test-' . uniqid();
        mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    public function test_creates_conventional_layout_structure(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $result = $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertDirectoryExists($this->testDir . '/src/phel');
        self::assertDirectoryExists($this->testDir . '/tests/phel');
        self::assertDirectoryExists($this->testDir . '/out');
        self::assertFileExists($this->testDir . '/phel-config.php');
        self::assertFileExists($this->testDir . '/src/phel/core.phel');
        self::assertFileExists($this->testDir . '/.gitignore');
    }

    public function test_creates_flat_layout_structure(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $result = $command->run(new ArrayInput([
            'project-name' => 'my-app',
            '--flat' => true,
        ]), $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertDirectoryExists($this->testDir . '/src');
        self::assertDirectoryExists($this->testDir . '/tests');
        self::assertDirectoryDoesNotExist($this->testDir . '/src/phel');
        self::assertFileExists($this->testDir . '/src/core.phel');
    }

    public function test_generated_config_contains_namespace(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        $configContent = file_get_contents($this->testDir . '/phel-config.php');

        self::assertStringContainsString("PhelConfig::forProject('myapp\\core')", (string) $configContent);
    }

    public function test_generated_config_uses_flat_layout(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput([
            'project-name' => 'test-project',
            '--flat' => true,
        ]), $output);

        $configContent = file_get_contents($this->testDir . '/phel-config.php');

        self::assertStringContainsString('->useFlatLayout()', (string) $configContent);
    }

    public function test_generated_core_file_contains_namespace(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        $coreContent = file_get_contents($this->testDir . '/src/phel/core.phel');

        self::assertStringContainsString('(ns myapp\\core)', (string) $coreContent);
        self::assertStringContainsString('(defn main []', (string) $coreContent);
        self::assertStringContainsString('Hello from Phel!', (string) $coreContent);
    }

    public function test_skips_existing_config_file(): void
    {
        $existingConfig = '<?php return [];';
        file_put_contents($this->testDir . '/phel-config.php', $existingConfig);

        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        $configContent = file_get_contents($this->testDir . '/phel-config.php');

        self::assertSame($existingConfig, $configContent);
        self::assertStringContainsString('already exists', $output->fetch());
    }

    public function test_skips_existing_core_file(): void
    {
        mkdir($this->testDir . '/src/phel', 0755, true);
        $existingCore = '(ns existing)';
        file_put_contents($this->testDir . '/src/phel/core.phel', $existingCore);

        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        $coreContent = file_get_contents($this->testDir . '/src/phel/core.phel');

        self::assertSame($existingCore, $coreContent);
    }

    public function test_default_project_name_is_app(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput([]), $output);

        $configContent = file_get_contents($this->testDir . '/phel-config.php');

        self::assertStringContainsString("PhelConfig::forProject('app\\core')", (string) $configContent);
    }

    public function test_namespace_conversion_removes_hyphens(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-cool-app']), $output);

        $coreContent = file_get_contents($this->testDir . '/src/phel/core.phel');

        self::assertStringContainsString('(ns mycoolapp\\core)', (string) $coreContent);
    }

    public function test_gitignore_contains_standard_entries(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        $gitignoreContent = file_get_contents($this->testDir . '/.gitignore');

        self::assertStringContainsString('/vendor/', (string) $gitignoreContent);
        self::assertStringContainsString('/out/', (string) $gitignoreContent);
        self::assertStringContainsString('/src/PhelGenerated/', (string) $gitignoreContent);
        self::assertStringContainsString('phel-config-local.php', (string) $gitignoreContent);
    }

    public function test_output_shows_next_steps(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        $outputContent = $output->fetch();

        self::assertStringContainsString('Phel project initialized successfully', $outputContent);
        self::assertStringContainsString('phel run', $outputContent);
        self::assertStringContainsString('phel repl', $outputContent);
        self::assertStringContainsString('phel build', $outputContent);
    }

    public function test_does_not_show_message_for_existing_gitignore(): void
    {
        file_put_contents($this->testDir . '/.gitignore', '*.log');

        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        $outputContent = $output->fetch();

        // Should not mention .gitignore since skipExistsMessage is true
        self::assertStringNotContainsString('.gitignore already exists', $outputContent);
    }

    public function test_dry_run_does_not_create_files(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $result = $command->run(new ArrayInput([
            'project-name' => 'my-app',
            '--dry-run' => true,
        ]), $output);

        self::assertSame(Command::SUCCESS, $result);
        self::assertFileDoesNotExist($this->testDir . '/phel-config.php');
        self::assertDirectoryDoesNotExist($this->testDir . '/src/phel');

        $outputContent = $output->fetch();

        self::assertStringContainsString('Dry run mode', $outputContent);
        self::assertStringContainsString('[DRY-RUN] Would create', $outputContent);
    }

    public function test_force_overwrites_existing_files(): void
    {
        $existingConfig = '<?php return [];';
        mkdir($this->testDir . '/src/phel', 0755, true);
        file_put_contents($this->testDir . '/phel-config.php', $existingConfig);

        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput([
            'project-name' => 'my-app',
            '--force' => true,
        ]), $output);

        $configContent = file_get_contents($this->testDir . '/phel-config.php');

        self::assertNotSame($existingConfig, $configContent);
        self::assertStringContainsString('PhelConfig::forProject', (string) $configContent);
        self::assertStringContainsString('Overwrote', $output->fetch());
    }

    public function test_skipping_message_mentions_force_option(): void
    {
        file_put_contents($this->testDir . '/phel-config.php', '<?php return [];');

        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput(['project-name' => 'my-app']), $output);

        self::assertStringContainsString('use --force to overwrite', $output->fetch());
    }

    public function test_no_gitignore_option_skips_gitignore(): void
    {
        $command = new InitCommand();
        $output = new BufferedOutput();

        chdir($this->testDir);
        $command->run(new ArrayInput([
            'project-name' => 'my-app',
            '--no-gitignore' => true,
        ]), $output);

        self::assertFileDoesNotExist($this->testDir . '/.gitignore');
        self::assertFileExists($this->testDir . '/phel-config.php');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
