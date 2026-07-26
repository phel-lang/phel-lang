<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use Phel\Shared\PhelProjectDirectory;

use const DIRECTORY_SEPARATOR;

/**
 * Resolves the REPL history path under `<projectRoot>/.phel/repl-history`.
 */
final readonly class ReplHistoryPathResolver
{
    public const string FILENAME = 'repl-history';

    public function __construct(
        private string $projectRoot,
    ) {}

    public function resolve(): string
    {
        return PhelProjectDirectory::ensure($this->projectRoot) . DIRECTORY_SEPARATOR . self::FILENAME;
    }
}
