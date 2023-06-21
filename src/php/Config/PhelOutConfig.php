<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;

final class PhelOutConfig implements JsonSerializable
{
    public const DEST_DIR = 'dir';
    public const MAIN_NAMESPACE = 'main-namespace';
    public const MAIN_FILENAME = 'main-filename';

    private string $destDir = 'out';
    private string $mainNamespace = '';
    private string $mainFilename = 'main';

    public static function fromArray(array $array): self
    {
        $self = new self();
        if (isset($array[self::DEST_DIR])) {
            $self->destDir = $array[self::DEST_DIR];
        }
        if (isset($array[self::MAIN_NAMESPACE])) {
            $self->mainNamespace = $array[self::MAIN_NAMESPACE];
        }
        if (isset($array[self::MAIN_FILENAME])) {
            $self->mainFilename = $array[self::MAIN_FILENAME];
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

    public function getMainNamespace(): string
    {
        return $this->mainNamespace;
    }

    public function setMainNamespace(string $mainNs): self
    {
        $this->mainNamespace = $mainNs;
        return $this;
    }

    public function getMainFilename(): string
    {
        return $this->mainFilename;
    }

    public function setMainFilename(string $mainFilename): self
    {
        $this->mainFilename = $mainFilename;
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            self::DEST_DIR => $this->getDestDir(),
            self::MAIN_NAMESPACE => $this->getMainNamespace(),
            self::MAIN_FILENAME => $this->getMainFilename(),
        ];
    }
}
