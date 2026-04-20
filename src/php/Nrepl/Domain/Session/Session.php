<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Session;

final class Session
{
    private mixed $lastValue = null;

    public function __construct(
        public readonly string $id,
        private string $namespace = 'user',
    ) {}

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $ns): void
    {
        $this->namespace = $ns;
    }

    public function lastValue(): mixed
    {
        return $this->lastValue;
    }

    public function recordValue(mixed $value): void
    {
        $this->lastValue = $value;
    }
}
