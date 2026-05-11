<?php

declare(strict_types=1);

namespace Phel\Filesystem\Application;

use Phel\Compiler\Domain\Evaluator\Exceptions\FileException;

use const DIRECTORY_SEPARATOR;
use const LOCK_EX;

/**
 * Owns the per-project `.phel/` directory: creates it on demand and
 * seeds a `.gitignore` so consumers never see the directory in
 * `git status`. Pattern borrowed from pytest / mypy / ruff.
 */
final class PhelProjectDirectory
{
    public const string DIRECTORY_NAME = '.phel';

    private const string GITIGNORE_FILENAME = '.gitignore';

    private const string GITIGNORE_CONTENT = "# Created automatically by Phel.\n*\n";

    /**
     * Creates `<projectRoot>/.phel/` if missing and seeds `.gitignore` once.
     * Existing `.gitignore` content is preserved.
     *
     * @throws FileException when the directory cannot be created
     *
     * @return string Absolute path to the `.phel/` directory.
     */
    public static function ensure(string $projectRoot): string
    {
        $dir = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY_NAME;

        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw FileException::canNotCreateDirectory($dir);
        }

        $gitignore = $dir . DIRECTORY_SEPARATOR . self::GITIGNORE_FILENAME;
        if (!file_exists($gitignore)) {
            @file_put_contents($gitignore, self::GITIGNORE_CONTENT, LOCK_EX);
        }

        return $dir;
    }
}
