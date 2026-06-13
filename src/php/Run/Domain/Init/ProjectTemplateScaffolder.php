<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Init;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

use function dirname;
use function implode;
use function is_dir;
use function ksort;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function trim;

/**
 * Scaffolds a new project from one of the bundled example apps in
 * `resources/agents/examples/` (the single source of truth — examples are not
 * duplicated). Every occurrence of the template's namespace base (its directory
 * name, e.g. `http-json-api`) is rewritten to the chosen project name across ns
 * forms, composer.json, entry points, and the README.
 */
final class ProjectTemplateScaffolder
{
    /** Template name => one-line description, shown by `--list-templates`. */
    private const array TEMPLATES = [
        'http-json-api' => 'Minimal HTTP JSON API (phel\http routing, handlers, public/index.php)',
        'todo-app' => 'HTTP todo app with an in-memory store and route handlers',
        'cli-wordcount' => 'CLI word-count tool reading stdin or file arguments',
    ];

    /**
     * @return array<string, string>
     */
    public function availableTemplates(): array
    {
        return self::TEMPLATES;
    }

    public function hasTemplate(string $name): bool
    {
        return isset(self::TEMPLATES[$name]);
    }

    /**
     * Builds the file set for a template, keyed by path relative to the project
     * root, with the namespace base rewritten to the project name.
     *
     * @return array<string, string>
     */
    public function files(string $templateName, string $projectName): array
    {
        if (!$this->hasTemplate($templateName)) {
            throw new RuntimeException(sprintf(
                'Unknown template "%s". Available templates: %s',
                $templateName,
                implode(', ', array_keys(self::TEMPLATES)),
            ));
        }

        $root = $this->locateExamplesRoot() . '/' . $templateName;
        $base = $this->namespaceBase($projectName);

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo) {
                continue;
            }

            if (!$fileInfo->isFile()) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $relativePath = substr($absolutePath, strlen($root) + 1);
            $content = (string) file_get_contents($absolutePath);

            $files[$relativePath] = str_replace($templateName, $base, $content);
        }

        ksort($files);

        return $files;
    }

    /**
     * Locate the bundled `resources/agents/examples/` directory. Levels differ
     * by install layout: 5 = Composer dependency (vendor/), 4 = this repo's own
     * checkout, 6 = nested edge cases. The examples subtree is shipped inside
     * phel.phar even though the rest of `resources/agents/` is not.
     */
    public function locateExamplesRoot(): string
    {
        foreach ([5, 4, 6] as $levels) {
            $candidate = dirname(__DIR__, $levels) . '/resources/agents/examples';
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Cannot locate bundled resources/agents/examples/ directory.');
    }

    /**
     * Turns a project name into a kebab-case namespace base (lowercase,
     * non-alphanumeric runs collapsed to a single hyphen). Hyphens are kept
     * because Phel namespaces allow them, matching the example layout.
     */
    private function namespaceBase(string $projectName): string
    {
        $kebab = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $projectName));
        $kebab = trim($kebab, '-');

        return $kebab === '' ? 'app' : $kebab;
    }
}
