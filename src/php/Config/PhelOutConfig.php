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

    private string $mainPhelNamespace = '';

    /** @deprecated in favor of $mainPhpPath */
    private string $destDir = '';

    /** @deprecated in favor of $mainPhpPath */
    private string $mainPhpFilename = '';

    private string $mainPhpPath = 'out/index.php';

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

    /**
     * @deprecated in favor of setMainPhpPath()
     */
    public function setDestDir(string $destDir): self
    {
        $this->destDir = $destDir;
        return $this;
    }

    public function getMainPhelNamespace(): string
    {
        return $this->mainPhelNamespace;
    }

    public function setMainPhelNamespace(string $namespace): self
    {
        $this->mainPhelNamespace = $namespace;
        return $this;
    }

    public function setMainPhpPath(string $path): self
    {
        $this->mainPhpPath = $path;
        return $this;
    }

    public function getMainPhpPath(): string
    {
        if ($this->destDir === '' && $this->mainPhpFilename === '') {
            return $this->mainPhpPath;
        }

        return sprintf(
            '%s/%s.php',
            $this->destDir !== '' && $this->destDir !== '0' ? $this->destDir : 'out',
            $this->mainPhpFilename !== '' && $this->mainPhpFilename !== '0' ? $this->mainPhpFilename : 'index',
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

    public function shouldCreateEntryPointPhpFile(): bool
    {
        return (bool)$this->mainPhelNamespace;
    }

    public function jsonSerialize(): array
    {
        return [
            self::MAIN_PHEL_NAMESPACE => $this->mainPhelNamespace,
            self::DEST_DIR => $this->destDir !== ''
                ? $this->destDir : explode('/', $this->mainPhpPath)[0],
            self::MAIN_PHP_FILENAME => $this->mainPhpFilename !== ''
                ? $this->mainPhpFilename : explode('/', $this->mainPhpPath)[1],
            self::MAIN_PHP_PATH => $this->getMainPhpPath(),
        ];
    }
}
