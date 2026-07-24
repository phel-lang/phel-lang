<?php

declare(strict_types=1);

namespace Phel\Lang;

use InvalidArgumentException;
use Phel\Lang\Collections\Map\PersistentMapInterface;

/**
 * @template T
 *
 * @extends AbstractType<T>
 */
final class Atom extends AbstractType
{
    use MetaTrait;

    /** @var array<string, callable(Keyword, self<T>, T, T): void> */
    private array $watches = [];

    /** @var (callable(T): mixed)|null */
    private $validator;

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     * @param T                                         $value
     */
    public function __construct(
        ?PersistentMapInterface $meta,
        private mixed $value,
    ) {
        $this->meta = $meta;
    }

    /**
     * @param T $value
     *
     * @return T
     */
    public function set(mixed $value): mixed
    {
        if ($this->validator !== null) {
            $this->validate($value);
        }

        $oldValue = $this->value;
        $this->value = $value;

        if ($this->watches !== []) {
            $this->notifyWatches($oldValue, $value);
        }

        return $this->value;
    }

    /**
     * @return T
     */
    public function deref()
    {
        return $this->value;
    }

    /**
     * @param callable(Keyword, self<T>, T, T): void $fn called after every successful
     *                                                   {@see self::set()} with the watch key,
     *                                                   this atom, the old and the new value
     */
    public function addWatch(string $key, callable $fn): void
    {
        $this->watches[$key] = $fn;
    }

    public function removeWatch(string $key): void
    {
        unset($this->watches[$key]);
    }

    /**
     * @param (callable(T): mixed)|null $fn the return value is checked for Phel truthiness,
     *                                      so any value is accepted
     */
    public function setValidator(?callable $fn): void
    {
        if ($fn !== null) {
            $this->validate($this->value, $fn);
        }

        $this->validator = $fn;
    }

    /**
     * @return (callable(T): mixed)|null
     */
    public function getValidator(): ?callable
    {
        return $this->validator;
    }

    /**
     * Atoms compare by identity (`===`), never by their dereferenced value.
     * This keeps watch callbacks and validators bound to the container itself,
     * so two distinct atoms holding equal values are still considered different.
     */
    public function equals(mixed $other): bool
    {
        return $this === $other;
    }

    public function hash(): int
    {
        return crc32(spl_object_hash($this));
    }

    /**
     * @param T                         $value
     * @param (callable(T): mixed)|null $validator
     */
    private function validate(mixed $value, ?callable $validator = null): void
    {
        $fn = $validator ?? $this->validator;
        if ($fn !== null && !Truthy::isTruthy($fn($value))) {
            throw new InvalidArgumentException('Atom validator rejected the value');
        }
    }

    /**
     * @param T $oldValue
     * @param T $newValue
     */
    private function notifyWatches(mixed $oldValue, mixed $newValue): void
    {
        foreach ($this->watches as $key => $callback) {
            $keyword = Keyword::create($key);
            $callback($keyword, $this, $oldValue, $newValue);
        }
    }
}
