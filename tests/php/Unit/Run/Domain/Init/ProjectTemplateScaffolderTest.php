<?php

declare(strict_types=1);

namespace PhelTest\Unit\Run\Domain\Init;

use Phel\Run\Domain\Init\ProjectTemplateScaffolder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProjectTemplateScaffolderTest extends TestCase
{
    private ProjectTemplateScaffolder $scaffolder;

    protected function setUp(): void
    {
        $this->scaffolder = new ProjectTemplateScaffolder();
    }

    public function test_lists_the_bundled_templates(): void
    {
        $templates = $this->scaffolder->availableTemplates();

        self::assertArrayHasKey('http-json-api', $templates);
        self::assertArrayHasKey('todo-app', $templates);
        self::assertArrayHasKey('cli-wordcount', $templates);
    }

    public function test_has_template(): void
    {
        self::assertTrue($this->scaffolder->hasTemplate('cli-wordcount'));
        self::assertFalse($this->scaffolder->hasTemplate('nope'));
    }

    public function test_files_rewrite_the_namespace_base(): void
    {
        $files = $this->scaffolder->files('cli-wordcount', 'my-tool');

        self::assertArrayHasKey('src/main.phel', $files);
        self::assertArrayHasKey('composer.json', $files);

        $main = $files['src/main.phel'];
        self::assertStringContainsString('(ns my-tool\\main', $main);
        self::assertStringNotContainsString('cli-wordcount', $main);
        self::assertStringContainsString('my-tool', $files['composer.json']);
    }

    public function test_files_collapse_non_alphanumeric_runs_to_hyphens(): void
    {
        $files = $this->scaffolder->files('cli-wordcount', 'My Cool API!');

        self::assertStringContainsString('(ns my-cool-api\\main', $files['src/main.phel']);
    }

    public function test_files_throws_for_unknown_template(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown template "ghost"');

        $this->scaffolder->files('ghost', 'my-app');
    }
}
