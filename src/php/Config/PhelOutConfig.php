<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;

/**
 * @psalm-suppress DeprecatedProperty
 */
final class PhelOutConfig implements JsonSerializable
{
    public const DEST_DIR = 'dir';

    public const MAIN_PHEL_NAMESPACE = 'main-phel-namespace';

    public const MAIN_PHP_FILENAME = 'main-php-filename';

    public const MAIN_PHP_PATH = 'main-php-path';

    private const DEST_DIR_DEFAULT = 'out';

    private const PHP_FILENAME_DEFAULT = 'index.php';

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
            return $this->mainPhpPath;
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

    public function setDestDir(string $destDir): self
    {
        $this->destDir = $destDir;
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
            return explode('/', $this->mainPhpPath)[0];
        }

        return self::DEST_DIR_DEFAULT;
    }

    private function getPhpFilename(): string
    {
        if ($this->mainPhpPath !== '') {
            return explode('/', $this->mainPhpPath)[1];
        }

        if ($this->mainPhpFilename !== '') {
            return rtrim($this->mainPhpFilename, '.php') . '.php';
        }

        return self::PHP_FILENAME_DEFAULT;
    }
}
