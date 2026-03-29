<?php

declare(strict_types=1);

namespace Phel\Lang;

use InvalidArgumentException;
use Phel\Lang\Collections\Map\PersistentMapInterface;

/**
 * @template T
 */
final class Variable extends AbstractType
{
    use MetaTrait;

    /** @var array<string, callable> */
    private array $watches = [];

    /** @var ?callable */
    private mixed $validator = null;

    /**
     * @param T $value
     */
    public function __construct(
        ?PersistentMapInterface $meta,
        private mixed $value,
    ) {
        $this->meta = $meta;
    }

    /**
     * @param T $value
     */
    public function set(mixed $value): void
    {
        $this->validate($value);
        $oldValue = $this->value;
        $this->value = $value;
        $this->notifyWatches($oldValue, $value);
    }

    /**
     * @return T
     */
    public function deref()
    {
        return $this->value;
    }

    public function addWatch(string $key, callable $fn): void
    {
        $this->watches[$key] = $fn;
    }

    public function removeWatch(string $key): void
    {
        unset($this->watches[$key]);
    }

    public function setValidator(?callable $fn): void
    {
        if ($fn !== null) {
            $this->validate($this->value, $fn);
        }

        $this->validator = $fn;
    }

    public function getValidator(): ?callable
    {
        return $this->validator;
    }

    public function equals(mixed $other): bool
    {
        return $this === $other;
    }

    public function hash(): int
    {
        return crc32(spl_object_hash($this));
    }

    private function validate(mixed $value, ?callable $validator = null): void
    {
        $fn = $validator ?? $this->validator;
        if ($fn !== null && !Truthy::isTruthy($fn($value))) {
            throw new InvalidArgumentException('Variable validator rejected the value');
        }
    }

    private function notifyWatches(mixed $oldValue, mixed $newValue): void
    {
        foreach ($this->watches as $key => $callback) {
            $keyword = Keyword::create($key);
            $callback($keyword, $this, $oldValue, $newValue);
        }
    }
}
