<?php

declare(strict_types=1);

namespace Phel\Config;

use JsonSerializable;

final class PhelOutConfig implements JsonSerializable
{
    public const DEST_DIR = 'dir';
    public const MAIN_NS = 'main-namespace';
    public const MAIN_FILENAME = 'main-filename';

    private string $destDir = 'out';
    private string $mainNs = '';
    private string $mainFilename = 'main';

    public static function fromArray(array $array): self
    {
        $self = new self();
        if (isset($array[self::DEST_DIR])) {
            $self->destDir = $array[self::DEST_DIR];
        }
        if (isset($array[self::MAIN_NS])) {
            $self->mainNs = $array[self::MAIN_NS];
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

    public function getMainNs(): string
    {
        return $this->mainNs;
    }

    public function setMainNs(string $mainNs): self
    {
        $this->mainNs = $mainNs;
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
            self::MAIN_NS => $this->getMainNs(),
            self::MAIN_FILENAME => $this->getMainFilename(),
        ];
    }
}
