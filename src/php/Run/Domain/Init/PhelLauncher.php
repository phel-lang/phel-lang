<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Init;

use function basename;
use function dirname;
use function escapeshellarg;
use function explode;
use function is_executable;
use function is_file;
use function preg_match;
use function realpath;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;
use const PATH_SEPARATOR;

/**
 * How the user started Phel, so printed commands can be pasted as they are.
 *
 * @internal
 */
final readonly class PhelLauncher
{
    private const string COMPOSER_BINARY = './vendor/bin/phel';

    private const string PATH_COMMAND = 'phel';

    private function __construct(
        private string $pharPath,
        private string $pathEnv,
    ) {}

    public static function composer(): self
    {
        return new self('', '');
    }

    /**
     * @param string $pharPath `Phar::running(false)`: empty outside a PHAR
     * @param string $pathEnv  the `PATH` variable, to name a PHAR installed as a command by its name
     */
    public static function fromPharPath(string $pharPath, string $pathEnv = ''): self
    {
        return new self($pharPath, $pathEnv);
    }

    public function isPhar(): bool
    {
        return $this->pharPath !== '';
    }

    public function command(string $cwd): string
    {
        if (!$this->isPhar()) {
            return self::COMPOSER_BINARY;
        }

        return $this->commandOnPath($cwd) ?? 'php ' . $this->shellQuote($this->pharPathFrom($cwd));
    }

    /**
     * The installation guide's `mv phel.phar /usr/local/bin/phel`: when the
     * first `PATH` match for `phel` is the PHAR itself, `phel` runs it. Only
     * that name: another one could be a shell builtin such as `test`.
     */
    private function commandOnPath(string $cwd): ?string
    {
        $phar = realpath($this->pharPath);
        if (basename($this->pharPath) !== self::PATH_COMMAND || $phar === false || !is_executable($phar)) {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $this->pathEnv) as $dir) {
            // An empty entry means the current directory.
            $candidate = ($dir === '' ? $cwd : $dir) . '/' . self::PATH_COMMAND;
            if (is_file($candidate)) {
                return realpath($candidate) === $phar ? self::PATH_COMMAND : null;
            }
        }

        return null;
    }

    private function pharPathFrom(string $cwd): string
    {
        $pharDir = realpath(dirname($this->pharPath));
        $phar = $pharDir === false
            ? $this->normalize($this->pharPath)
            : rtrim($this->normalize($pharDir), '/') . '/' . basename($this->pharPath);
        $base = rtrim($this->normalize(realpath($cwd) ?: $cwd), '/') . '/';

        if (!str_starts_with($phar, $base)) {
            return $phar;
        }

        $relative = substr($phar, strlen($base));

        // A leading `-` would reach `php` as an option.
        return str_starts_with($relative, '-') ? './' . $relative : $relative;
    }

    /**
     * On POSIX a backslash is a valid file name character, not a separator.
     */
    private function normalize(string $path): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? str_replace('\\', '/', $path) : $path;
    }

    private function shellQuote(string $path): string
    {
        return preg_match('#^[\w.:/][\w.:/-]*$#', $path) === 1 ? $path : escapeshellarg($path);
    }
}
