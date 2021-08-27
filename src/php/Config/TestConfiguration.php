<?php

declare(strict_types=1);

namespace Phel\Config;

final class TestConfiguration
{
    private array $directories = [];

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param string|array $directories
     */
    public function setDirectories($directories): self
    {
        if (is_string($directories)) {
            $directories = [$directories];
        }

        $this->directories = $directories;

        return $this;
    }

    public function getDirectories(): array
    {
        return $this->directories;
    }
}
