<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Session;

use function array_slice;

/**
 * Mutable state for a single nREPL session: the current namespace scope and a
 * small ring of the most recently evaluated values, surfaced to clients as
 * `*1`/`*2`/`*3`.
 */
final class Session
{
    private const int HISTORY_SIZE = 3;

    /** @var list<mixed> the last few evaluated values, newest first */
    private array $valueHistory = [];

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
     * The value of the most recent successful evaluation (`*1`), or null.
     */
    public function lastValue(): mixed
    {
        return $this->value(1);
    }

    /**
     * The value $position evaluations ago — 1 is the most recent (`*1`) through
     * 3 (`*3`) — or null when the session has not produced that many yet.
     */
    public function value(int $position): mixed
    {
        return $this->valueHistory[$position - 1] ?? null;
    }

    /**
     * Record the latest evaluated value, rotating the `*1`/`*2`/`*3` history
     * and dropping anything older than the most recent {@see HISTORY_SIZE}.
     */
    public function recordValue(mixed $value): void
    {
        $this->valueHistory = array_slice([$value, ...$this->valueHistory], 0, self::HISTORY_SIZE);
    }
}
