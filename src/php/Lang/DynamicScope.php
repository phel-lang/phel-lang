<?php

declare(strict_types=1);

namespace Phel\Lang;

use Closure;
use Fiber;
use WeakMap;

use function array_key_exists;
use function array_pop;
use function array_reverse;

/**
 * Fiber-local stack of dynamic bindings.
 *
 * Clojure's `binding` establishes thread-local values for vars tagged
 * `:dynamic`. In Phel we model that per-PHP-fiber: each fiber owns its
 * own LIFO stack of frames, and the main (non-fiber) context has its
 * own stack. This is the backing store for both the `binding` macro
 * and "binding conveyance" into `future`/`async` bodies.
 */
final class DynamicScope
{
    private static ?DynamicScope $instance = null;

    /**
     * Stack used when no fiber is running.
     *
     * @var list<array<string, mixed>>
     */
    private array $mainStack = [];

    /** @var WeakMap<Fiber, list<array<string, mixed>>> */
    private WeakMap $fiberStacks;

    /**
     * Per-fiber stack of "binding recordings" — a recording is opened
     * when a `binding` macro starts and closed when it commits. Each
     * entry: `['dynamic' => array<string,mixed>, 'redefs' => list<array{string,string,mixed}>]`.
     *
     * @var list<array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>
     */
    private array $mainRecordings = [];

    /** @var WeakMap<Fiber, list<array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>> */
    private WeakMap $fiberRecordings;

    private function __construct()
    {
        /** @var WeakMap<Fiber, list<array<string, mixed>>> $map */
        $map = new WeakMap();
        $this->fiberStacks = $map;
        /** @var WeakMap<Fiber, list<array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>> $recMap */
        $recMap = new WeakMap();
        $this->fiberRecordings = $recMap;
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function clear(): void
    {
        $this->mainStack = [];
        $this->mainRecordings = [];
        /** @var WeakMap<Fiber, list<array<string, mixed>>> $map */
        $map = new WeakMap();
        $this->fiberStacks = $map;
        /** @var WeakMap<Fiber, list<array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>> $recMap */
        $recMap = new WeakMap();
        $this->fiberRecordings = $recMap;
    }

    /**
     * Open a new binding recording on the current fiber's stack.
     * Subsequent `recordDynamic` / `recordRedef` calls append to it
     * until `popRecording` is called.
     */
    public function startRecording(): void
    {
        $this->pushRecording(['dynamic' => [], 'redefs' => []]);
    }

    public function isRecording(): bool
    {
        return $this->currentRecordings() !== [];
    }

    public function recordDynamic(string $ns, string $name, mixed $value): void
    {
        $entry = $this->popTopRecording();
        if ($entry === null) {
            return;
        }

        $entry['dynamic'][$ns . '/' . $name] = $value;
        $this->pushRecording($entry);
    }

    public function recordRedef(string $ns, string $name, mixed $previous): void
    {
        $entry = $this->popTopRecording();
        if ($entry === null) {
            return;
        }

        $entry['redefs'][] = [$ns, $name, $previous];
        $this->pushRecording($entry);
    }

    /**
     * @return array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}
     */
    public function popRecording(): array
    {
        return $this->popTopRecording() ?? ['dynamic' => [], 'redefs' => []];
    }

    /**
     * @param array<string, mixed> $frame ["ns/name" => value]
     */
    public function pushFrame(array $frame): void
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            $this->mainStack[] = $frame;
            return;
        }

        $stack = $this->fiberStacks[$fiber] ?? [];
        $stack[] = $frame;
        $this->fiberStacks[$fiber] = $stack;
    }

    public function popFrame(): void
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            array_pop($this->mainStack);
            return;
        }

        if (!isset($this->fiberStacks[$fiber])) {
            return;
        }

        /** @var list<array<string, mixed>> $stack */
        $stack = $this->fiberStacks[$fiber];
        array_pop($stack);
        if ($stack === []) {
            unset($this->fiberStacks[$fiber]);
        } else {
            $this->fiberStacks[$fiber] = $stack;
        }
    }

    public function hasBinding(string $ns, string $name): bool
    {
        $key = $ns . '/' . $name;
        return array_any(array_reverse($this->currentStack()), static fn($frame): bool => array_key_exists($key, $frame));
    }

    public function getBinding(string $ns, string $name): mixed
    {
        $key = $ns . '/' . $name;
        foreach (array_reverse($this->currentStack()) as $frame) {
            if (array_key_exists($key, $frame)) {
                return $frame[$key];
            }
        }

        return null;
    }

    /**
     * Flatten the current stack into a single map (innermost value wins).
     * Used for binding conveyance into futures.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $result = [];
        foreach ($this->currentStack() as $frame) {
            foreach ($frame as $key => $value) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Push a frame, execute the closure, always pop on return or throw.
     *
     * @param array<string, mixed> $frame
     */
    public function withFrame(array $frame, Closure $fn): mixed
    {
        $this->pushFrame($frame);
        try {
            return $fn();
        } finally {
            $this->popFrame();
        }
    }

    /**
     * @param array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>} $entry
     */
    private function pushRecording(array $entry): void
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            $stack = $this->mainRecordings;
            $stack[] = $entry;
            $this->mainRecordings = $stack;
            return;
        }

        /** @var list<array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}> $stack */
        $stack = $this->fiberRecordings[$fiber] ?? [];
        $stack[] = $entry;
        $this->fiberRecordings[$fiber] = $stack;
    }

    /**
     * @return array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}|null
     */
    private function popTopRecording(): ?array
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            return array_pop($this->mainRecordings);
        }

        if (!isset($this->fiberRecordings[$fiber])) {
            return null;
        }

        /** @var list<array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}> $stack */
        $stack = $this->fiberRecordings[$fiber];
        $entry = array_pop($stack);
        if ($stack === []) {
            unset($this->fiberRecordings[$fiber]);
        } else {
            $this->fiberRecordings[$fiber] = $stack;
        }

        return $entry;
    }

    /**
     * @return list<array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>
     */
    private function currentRecordings(): array
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            return $this->mainRecordings;
        }

        /** @var list<array{dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}> $stack */
        $stack = $this->fiberRecordings[$fiber] ?? [];
        return $stack;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function currentStack(): array
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            return $this->mainStack;
        }

        /** @var list<array<string, mixed>> $stack */
        $stack = $this->fiberStacks[$fiber] ?? [];
        return $stack;
    }
}
