<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Struct;

use InvalidArgumentException;
use Override;
use Phel;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\Map\AbstractPersistentMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Traversable;

use function count;
use function in_array;
use function sprintf;

/**
 * @template V
 *
 * @extends AbstractPersistentMap<Keyword, V>
 */
abstract class AbstractPersistentStruct extends AbstractPersistentMap
{
    protected const array ALLOWED_KEYS = [];

    private StructKeyEncoder $keyEncoder;

    public function __construct()
    {
        parent::__construct(
            TypeFactory::getInstance()->getHasher(),
            TypeFactory::getInstance()->getEqualizer(),
            null,
        );
        $this->keyEncoder = new StructKeyEncoder();
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        $newInstance = clone $this;
        $newInstance->meta = $meta;
        return $newInstance;
    }

    public function contains($key): bool
    {
        if (!$key instanceof Keyword) {
            return false;
        }

        return in_array($key->getName(), static::ALLOWED_KEYS, true);
    }

    public function put($key, $value): PersistentMapInterface
    {
        $stringKey = $this->validateKey($key);

        $newInstance = clone $this;
        $newInstance->{$stringKey} = $value;
        return $newInstance;
    }

    public function remove($key): PersistentMapInterface
    {
        if (!$this->contains($key)) {
            return $this;
        }

        return $this->toPersistentMapWithout($key);
    }

    public function count(): int
    {
        return count(static::ALLOWED_KEYS);
    }

    public function find($key)
    {
        $stringKey = $this->validateKey($key);
        return $this->{$stringKey};
    }

    public function getIterator(): Traversable
    {
        foreach (static::ALLOWED_KEYS as $key) {
            yield Phel::keyword($key) => $this->{$this->keyEncoder->encode($key)};
        }
    }

    public function asTransient(): never
    {
        throw new MethodNotSupportedException("Structs don't support transients");
    }

    #[Override]
    public function equals(mixed $other): bool
    {
        if (!$other instanceof static) {
            return false;
        }

        return parent::equals($other);
    }

    public function getAllowedKeys(): array
    {
        return array_map(
            static fn(string $k): Keyword => Phel::keyword($k),
            static::ALLOWED_KEYS,
        );
    }

    protected function validateKey(Keyword $key): string
    {
        if (in_array($key->getName(), static::ALLOWED_KEYS)) {
            return $this->keyEncoder->encode($key->getName());
        }

        $structName = static::class;
        throw new InvalidArgumentException(sprintf("This key '%s' is not allowed for struct %s", (string) $key, $structName));
    }

    private function toPersistentMapWithout(Keyword $key): PersistentMapInterface
    {
        $kvs = [];
        foreach (static::ALLOWED_KEYS as $allowedKey) {
            $entryKey = Phel::keyword($allowedKey);
            if ($this->equalizer->equals($entryKey, $key)) {
                continue;
            }

            $kvs[] = $entryKey;
            $kvs[] = $this->{$this->keyEncoder->encode($allowedKey)};
        }

        return TypeFactory::getInstance()->persistentMapFromArray($kvs);
    }
}
