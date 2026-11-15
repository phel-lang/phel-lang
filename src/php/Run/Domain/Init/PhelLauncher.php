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

        return $this->commandOnPath() ?? 'php ' . $this->shellQuote($this->pharPathFrom($cwd));
    }

    /**
     * The installation guide's `mv phel.phar /usr/local/bin/phel`: the first
     * `PATH` match for the PHAR's name is the PHAR itself, so its name runs it.
     */
    private function commandOnPath(): ?string
    {
        $name = basename($this->pharPath);
        $phar = realpath($this->pharPath);
        if ($phar === false || !is_executable($phar) || preg_match('#^[\w.][\w.-]*$#', $name) !== 1) {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $this->pathEnv) as $dir) {
            if ($dir !== '' && is_file($dir . '/' . $name)) {
                return realpath($dir . '/' . $name) === $phar ? $name : null;
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
