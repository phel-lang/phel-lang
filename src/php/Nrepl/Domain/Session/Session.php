<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Session;

/**
 * Mutable state for a single nREPL session: tracks the current namespace
 * scope and the most recently evaluated value (the future basis for *1/*2/*3
 * history; for now only the single last value is kept).
 */
final class Session
{
    private mixed $lastValue = null;

    public function __construct(
        public readonly string $id,
        private string $namespace = 'user',
    ) {}

    /**
     * The namespace eval ops run in for this session.
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * Switch the session's active namespace (e.g. after an `in-ns` form).
     */
    public function setNamespace(string $ns): void
    {
        $this->namespace = $ns;
    }

    /**
     * The value of the most recent successful evaluation in this session.
     */
    public function lastValue(): mixed
    {
        return $this->lastValue;
    }

    /**
     * Remember the latest evaluated value as the session's last value.
     */
    public function recordValue(mixed $value): void
    {
        $this->lastValue = $value;
    }
}
