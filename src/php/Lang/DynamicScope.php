<?php

declare(strict_types=1);

namespace Phel\Lang;

use Closure;
use Fiber;
use WeakMap;

use function array_key_exists;
use function array_pop;
use function array_reverse;
use function count;

/**
 * Fiber-local stack of dynamic bindings.
 *
 * `binding` establishes thread-local values for vars tagged `:dynamic`.
 * In Phel we model that per-PHP-fiber: each fiber owns its own LIFO
 * stack of frames, and the main (non-fiber) context has its own stack.
 * This is the backing store for both the `binding` macro and "binding
 * conveyance" into `future`/`async` bodies.
 */
final class DynamicScope
{
    public const string MODE_BINDING = 'binding';

    public const string MODE_REDEFS = 'redefs';

    private static ?DynamicScope $instance = null;

    /**
     * Stack used when no fiber is running.
     *
     * @var list<array<string, mixed>>
     */
    private array $mainStack = [];

    /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, list<array<string, mixed>>> */
    private WeakMap $fiberStacks;

    /**
     * Per-fiber stack of "binding recordings" — a recording is opened
     * when a `binding` or `with-redefs` macro starts and closed when
     * it commits. Each entry: `['mode' => 'binding'|'redefs',
     * 'dynamic' => array<string,mixed>,
     * 'redefs' => list<array{string,string,mixed}>]`.
     *
     * @var list<array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>
     */
    private array $mainRecordings = [];

    /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, list<array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>> */
    private WeakMap $fiberRecordings;

    private function __construct()
    {
        /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, list<array<string, mixed>>> $map */
        $map = new WeakMap();
        $this->fiberStacks = $map;
        /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, list<array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>> $recMap */
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
        /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, list<array<string, mixed>>> $map */
        $map = new WeakMap();
        $this->fiberStacks = $map;
        /** @var WeakMap<Fiber<mixed, mixed, mixed, mixed>, list<array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>> $recMap */
        $recMap = new WeakMap();
        $this->fiberRecordings = $recMap;
    }

    /**
     * Open a new binding recording on the current fiber's stack.
     * Subsequent `recordDynamic` / `recordRedef` calls append to it
     * until `popRecording` is called. The mode controls which kind of
     * mutation is allowed: `binding` enforces `:dynamic`-only vars at
     * the call site, `redefs` accepts any var.
     */
    public function startRecording(string $mode = self::MODE_BINDING): void
    {
        $this->pushRecording(['mode' => $mode, 'dynamic' => [], 'redefs' => []]);
    }

    public function isRecording(): bool
    {
        return $this->currentRecordings() !== [];
    }

    /**
     * Returns the mode of the topmost open recording, or null when
     * nothing is being recorded on the current fiber.
     */
    public function currentRecordingMode(): ?string
    {
        $recordings = $this->currentRecordings();
        if ($recordings === []) {
            return null;
        }

        return $recordings[count($recordings) - 1]['mode'];
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
     * @return array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}
     */
    public function popRecording(): array
    {
        return $this->popTopRecording() ?? ['mode' => self::MODE_BINDING, 'dynamic' => [], 'redefs' => []];
    }

    /**
     * @param array<string, mixed> $frame ["ns/name" => value]
     */
    public function pushFrame(array $frame): void
    {
        /** @var Fiber<mixed, mixed, mixed, mixed>|null $fiber */
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
        /** @var Fiber<mixed, mixed, mixed, mixed>|null $fiber */
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

    /**
     * O(1) probe used by callers that want to short-circuit the common
     * "no dynamic binding active" case before paying the
     * {@see self::hasBinding()} / {@see self::getBinding()} cost on the
     * hot global-read path.
     */
    public function hasAnyBinding(): bool
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            return $this->mainStack !== [];
        }

        return isset($this->fiberStacks[$fiber]);
    }

    public function hasBinding(string $ns, string $name): bool
    {
        $stack = $this->currentStack();
        if ($stack === []) {
            return false;
        }

        $key = $ns . '/' . $name;
        return array_any(array_reverse($stack), static fn($frame): bool => array_key_exists($key, $frame));
    }

    public function getBinding(string $ns, string $name): mixed
    {
        $stack = $this->currentStack();
        if ($stack === []) {
            return null;
        }

        $key = $ns . '/' . $name;
        foreach (array_reverse($stack) as $frame) {
            if (array_key_exists($key, $frame)) {
                return $frame[$key];
            }
        }

        return null;
    }

    /**
     * Mutate the topmost active frame slot for the given var. Returns
     * true when a frame holding the slot was found and updated, false
     * when no fiber-local frame currently overrides the var. Backing
     * store for `var-set`.
     */
    public function setBinding(string $ns, string $name, mixed $value): bool
    {
        /** @var Fiber<mixed, mixed, mixed, mixed>|null $fiber */
        $fiber = Fiber::getCurrent();
        $key = $ns . '/' . $name;

        if (!$fiber instanceof Fiber) {
            $updated = $this->writeTopmostFrame($this->mainStack, $key, $value);
            if ($updated === null) {
                return false;
            }

            $this->mainStack = $updated;
            return true;
        }

        if (!isset($this->fiberStacks[$fiber])) {
            return false;
        }

        /** @var list<array<string, mixed>> $stack */
        $stack = $this->fiberStacks[$fiber];
        $updated = $this->writeTopmostFrame($stack, $key, $value);
        if ($updated === null) {
            return false;
        }

        $this->fiberStacks[$fiber] = $updated;
        return true;
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
     * @param array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>} $entry
     */
    private function pushRecording(array $entry): void
    {
        /** @var Fiber<mixed, mixed, mixed, mixed>|null $fiber */
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            $stack = $this->mainRecordings;
            $stack[] = $entry;
            $this->mainRecordings = $stack;
            return;
        }

        /** @var list<array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}> $stack */
        $stack = $this->fiberRecordings[$fiber] ?? [];
        $stack[] = $entry;
        $this->fiberRecordings[$fiber] = $stack;
    }

    /**
     * @return array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}|null
     */
    private function popTopRecording(): ?array
    {
        /** @var Fiber<mixed, mixed, mixed, mixed>|null $fiber */
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            return array_pop($this->mainRecordings);
        }

        if (!isset($this->fiberRecordings[$fiber])) {
            return null;
        }

        /** @var list<array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}> $stack */
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
     * @return list<array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}>
     */
    private function currentRecordings(): array
    {
        $fiber = Fiber::getCurrent();
        if (!$fiber instanceof Fiber) {
            return $this->mainRecordings;
        }

        /** @var list<array{mode: string, dynamic: array<string, mixed>, redefs: list<array{0: string, 1: string, 2: mixed}>}> $stack */
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

    /**
     * Walk the stack from top to bottom and return a new stack with the
     * first frame holding `$key` mutated to `$value`. Returns null when
     * no frame holds the key.
     *
     * @param list<array<string, mixed>> $stack
     *
     * @return list<array<string, mixed>>|null
     */
    private function writeTopmostFrame(array $stack, string $key, mixed $value): ?array
    {
        for ($i = count($stack) - 1; $i >= 0; --$i) {
            if (array_key_exists($key, $stack[$i])) {
                $stack[$i][$key] = $value;
                return $stack;
            }
        }

        return null;
    }
}
