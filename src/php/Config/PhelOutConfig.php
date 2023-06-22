<?php

declare(strict_types=1);

namespace Phel\Config;

use Gacela\Framework\Gacela;
use JsonSerializable;

final class PhelOutConfig implements JsonSerializable
{
    public const DEST_DIR = 'dir';
    public const MAIN_PHEL_NAMESPACE = 'main-phel-namespace';
    public const MAIN_PHP_FILENAME = 'main-php-filename';
    public const MAIN_PHP_PATH = 'main-php-path';

    private string $destDir = 'out';
    private string $mainPhelNamespace = '';
    private string $mainPhpFilename = 'index';

    public static function fromArray(array $array): self
    {
        $self = new self();
        if (isset($array[self::DEST_DIR])) {
            $self->destDir = $array[self::DEST_DIR];
        }
        if (isset($array[self::MAIN_PHEL_NAMESPACE])) {
            $self->mainPhelNamespace = $array[self::MAIN_PHEL_NAMESPACE];
        }
        if (isset($array[self::MAIN_PHP_FILENAME])) {
            $self->mainPhpFilename = $array[self::MAIN_PHP_FILENAME];
        }

        return $self;
    }

    public function getDestDir(): string
    {
        return $this->destDir;
    }

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

    public function getMainPhpPath(): string
    {
        return Gacela::rootDir() . "/{$this->destDir}/{$this->mainPhpFilename}.php";
    }

    public function setMainPhpFilename(string $name): self
    {
        $this->mainPhpFilename = $name;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            self::DEST_DIR => $this->getDestDir(),
            self::MAIN_PHEL_NAMESPACE => $this->getMainPhelNamespace(),
            self::MAIN_PHP_FILENAME => $this->mainPhpFilename,
            self::MAIN_PHP_PATH => $this->getMainPhpPath(),
        ];
    }
}
