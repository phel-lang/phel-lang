<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Session;

use Phel\Nrepl\Domain\Inspection\ValueInspection;
use RuntimeException;

use function array_slice;
use function count;

/**
 * Mutable state for a single nREPL session: the current namespace scope and a
 * small ring of the most recently evaluated values, surfaced to clients as
 * `*1`/`*2`/`*3`.
 *
 * @internal
 */
final class Session
{
    public const string DEFAULT_NAMESPACE = 'user';

    public const int MAX_INSPECTIONS = 8;

    private const int HISTORY_SIZE = 3;

    /** @var array<string, ValueInspection> */
    private array $inspections = [];

    /** @var list<mixed> the last few evaluated values, newest first */
    private array $valueHistory = [];

    public function __construct(
        public readonly string $id,
        private string $namespace = self::DEFAULT_NAMESPACE,
    ) {}

    public function requireInspectionCapacity(): void
    {
        if (count($this->inspections) >= self::MAX_INSPECTIONS) {
            throw new RuntimeException('Inspection limit reached. Release an inspection first.');
        }
    }

    public function inspect(mixed $value): ValueInspection
    {
        $this->requireInspectionCapacity();
        $inspection = new ValueInspection($value);
        $this->inspections[$inspection->id()] = $inspection;
        return $inspection;
    }

    public function inspection(string $id): ValueInspection
    {
        return $this->inspections[$id] ?? throw new RuntimeException('Unknown or released inspection.');
    }

    public function releaseInspection(string $id): void
    {
        $inspection = $this->inspection($id);
        unset($this->inspections[$id]);
        $inspection->release();
    }

    public function clearInspections(): void
    {
        $inspections = $this->inspections;
        $this->inspections = [];
        foreach ($inspections as $inspection) {
            $inspection->release();
        }
    }

    /**
     * The namespace this session last evaluated in. Evaluation itself runs in
     * the compiler's global environment, which every session shares; this is
     * the mirror of it that `EvalResultResponder` reports as the `ns` response
     * field and `LookupOp` resolves unqualified symbols against.
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
