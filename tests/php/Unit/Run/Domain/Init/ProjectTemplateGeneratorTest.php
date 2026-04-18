<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Init;

use Phel\Config\ProjectLayout;
use Phel\Run\Domain\Init\ProjectTemplateGenerator;
use PHPUnit\Framework\TestCase;

final class ProjectTemplateGeneratorTest extends TestCase
{
    private ProjectTemplateGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ProjectTemplateGenerator();
    }

    public function test_generate_config_flat_layout(): void
    {
        $config = $this->generator->generateConfig('myapp\\main', ProjectLayout::Flat);

        self::assertStringContainsString('use Phel\\Config\\PhelConfig;', $config);
        self::assertStringContainsString("PhelConfig::forProject(mainNamespace: 'myapp\\main')", $config);
        self::assertStringNotContainsString('ProjectLayout::', $config);
    }

    public function test_generate_config_nested_layout(): void
    {
        $config = $this->generator->generateConfig('myapp\\main', ProjectLayout::Nested);

        self::assertStringContainsString('use Phel\\Config\\PhelConfig;', $config);
        self::assertStringContainsString('use Phel\\Config\\ProjectLayout;', $config);
        self::assertStringContainsString("mainNamespace: 'myapp\\main'", $config);
        self::assertStringContainsString('layout: ProjectLayout::Nested', $config);
    }

    public function test_generate_config_root_layout(): void
    {
        $config = $this->generator->generateConfig('sandbox\\main', ProjectLayout::Root);

        self::assertStringContainsString('use Phel\\Config\\PhelConfig;', $config);
        self::assertStringContainsString('use Phel\\Config\\ProjectLayout;', $config);
        self::assertStringContainsString("mainNamespace: 'sandbox\\main'", $config);
        self::assertStringContainsString('layout: ProjectLayout::Root', $config);
    }

    public function test_generate_main_file(): void
    {
        $main = $this->generator->generateMainFile('myapp\\main');

        self::assertStringContainsString('(ns myapp\\main)', $main);
        self::assertStringContainsString('(defn greet [name]', $main);
        self::assertStringContainsString('(defn main []', $main);
        self::assertStringContainsString('println', $main);
        self::assertStringContainsString('(main)', $main);
    }

    public function test_generate_test_file(): void
    {
        $test = $this->generator->generateTestFile('myapp\\main');

        self::assertStringContainsString('(ns myapp\\main-test', $test);
        self::assertStringContainsString('phel\\test', $test);
        self::assertStringContainsString(':refer [deftest is]', $test);
        self::assertStringContainsString(':refer [greet]', $test);
        self::assertStringContainsString('(deftest test-greet', $test);
        self::assertStringContainsString('(is (= "Hello, Phel!"', $test);
    }

    public function test_generate_gitignore(): void
    {
        $gitignore = $this->generator->generateGitignore();

        self::assertStringContainsString('/vendor/', $gitignore);
        self::assertStringContainsString('/out/', $gitignore);
        self::assertStringContainsString('/src/PhelGenerated/', $gitignore);
        self::assertStringContainsString('*.phar', $gitignore);
        self::assertStringContainsString('.phpunit.result.cache', $gitignore);
        self::assertStringContainsString('phel-config-local.php', $gitignore);
    }

    public function test_generate_gitignore_for_root_layout_omits_phel_generated(): void
    {
        $gitignore = $this->generator->generateGitignore(ProjectLayout::Root);

        self::assertStringContainsString('/vendor/', $gitignore);
        self::assertStringContainsString('/out/', $gitignore);
        self::assertStringContainsString('*.phar', $gitignore);
        self::assertStringNotContainsString('/src/PhelGenerated/', $gitignore);
    }
}
