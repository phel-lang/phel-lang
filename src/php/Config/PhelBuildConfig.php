<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;

use function count;

/**
 * @psalm-suppress DeprecatedProperty
 */
final class PhelBuildConfig implements JsonSerializable
{
    public const DEST_DIR = 'dir';

    public const MAIN_PHEL_NAMESPACE = 'main-phel-namespace';

    public const MAIN_PHP_FILENAME = 'main-php-filename';

    public const MAIN_PHP_PATH = 'main-php-path';

    private const DEFAULT_DEST_DIR = 'out';

    private const DEFAULT_PHP_FILENAME = 'index.php';

    private string $mainPhelNamespace = '';

    private string $destDir = '';

    /** @deprecated in favor of $mainPhpPath */
    private string $mainPhpFilename = '';

    private string $mainPhpPath = '';

    public static function fromArray(array $array): self
    {
        $self = new self();
        if (isset($array[self::MAIN_PHEL_NAMESPACE])) {
            $self->mainPhelNamespace = $array[self::MAIN_PHEL_NAMESPACE];
        }

        if (isset($array[self::DEST_DIR])) {
            $self->destDir = $array[self::DEST_DIR];
        }

        if (isset($array[self::MAIN_PHP_FILENAME])) {
            $self->mainPhpFilename = $array[self::MAIN_PHP_FILENAME];
        }

        if (isset($array[self::MAIN_PHP_PATH])) {
            $self->mainPhpPath = $array[self::MAIN_PHP_PATH];
        }

        return $self;
    }

    public function jsonSerialize(): array
    {
        return [
            self::MAIN_PHEL_NAMESPACE => $this->mainPhelNamespace,
            self::DEST_DIR => $this->getDestDir(),
            self::MAIN_PHP_FILENAME => $this->getPhpFilename(),
            self::MAIN_PHP_PATH => $this->getMainPhpPath(),
        ];
    }

    public function setMainPhelNamespace(string $namespace): self
    {
        $this->mainPhelNamespace = $namespace;
        return $this;
    }

    public function getMainPhelNamespace(): string
    {
        return $this->mainPhelNamespace;
    }

    public function setMainPhpPath(string $path): self
    {
        $this->mainPhpPath = rtrim($path, '.php') . '.php';
        return $this;
    }

    public function getMainPhpPath(): string
    {
        if ($this->mainPhpPath !== '') {
            $explode = explode('/', $this->mainPhpPath);
            if (count($explode) === 2) {
                return $this->mainPhpPath;
            }
        }

        return sprintf(
            '%s/%s',
            $this->getDestDir(),
            $this->getPhpFilename(),
        );
    }

    /**
     * @deprecated in favor of setMainPhpPath()
     */
    public function setMainPhpFilename(string $name): self
    {
        $this->mainPhpFilename = $name;
        return $this;
    }

    public function setDestDir(string $dir): self
    {
        $this->destDir = $dir;
        return $this;
    }

    public function shouldCreateEntryPointPhpFile(): bool
    {
        return (bool)$this->mainPhelNamespace;
    }

    private function getDestDir(): string
    {
        if ($this->destDir !== '') {
            return $this->destDir;
        }

        if ($this->mainPhpPath !== '') {
            $explode = explode('/', $this->mainPhpPath);
            if (count($explode) === 1) {
                return self::DEFAULT_DEST_DIR;
            }

            return $explode[0];
        }

        return self::DEFAULT_DEST_DIR;
    }

    private function getPhpFilename(): string
    {
        if ($this->mainPhpPath !== '') {
            $explode = explode('/', $this->mainPhpPath);
            if (count($explode) === 1) {
                return $explode[0];
            }

            return $explode[1];
        }

        if ($this->mainPhpFilename !== '') {
            return rtrim($this->mainPhpFilename, '.php') . '.php';
        }

        return self::DEFAULT_PHP_FILENAME;
    }
}
