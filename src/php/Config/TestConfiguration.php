<?php

declare(strict_types=1);

namespace Phel\Config;

final class TestConfiguration
{
    /** @var list<string> */
    private array $directories = [];

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return list<string>
     */
    public function getDirectories(): array
    {
        return $this->directories;
    }

    public function setDirectories(string ...$directories): self
    {
        $this->directories = $directories;

        return $this;
    }
}
