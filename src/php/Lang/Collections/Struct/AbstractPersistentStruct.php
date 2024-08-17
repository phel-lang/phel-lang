<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Struct;

use InvalidArgumentException;
use Phel\Compiler\Application\Munge;
use Phel\Compiler\Domain\Emitter\OutputEmitter\MungeInterface;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\Map\AbstractPersistentMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
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
    protected const ALLOWED_KEYS = [];

    private MungeInterface $munge;

    public function __construct()
    {
        parent::__construct(
            TypeFactory::getInstance()->getHasher(),
            TypeFactory::getInstance()->getEqualizer(),
            null,
        );
        $this->munge = new Munge();
    }

    public function withMeta(?PersistentMapInterface $meta): static
    {
        $newInstance = clone $this;
        $newInstance->meta = $meta;
        return $newInstance;
    }

    public function contains($key): bool
    {
        return in_array($key->getName(), static::ALLOWED_KEYS);
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
        $stringKey = $this->validateKey($key);

        $newInstance = clone $this;
        $newInstance->{$stringKey} = null;
        return $newInstance;
    }

    public function count(): int
    {
        return is_countable(static::ALLOWED_KEYS) ? count(static::ALLOWED_KEYS) : 0;
    }

    public function find($key)
    {
        $stringKey = $this->validateKey($key);
        return $this->{$stringKey};
    }

    public function getIterator(): Traversable
    {
        foreach (static::ALLOWED_KEYS as $key) {
            yield TypeFactory::getInstance()->keyword($key) => $this->{$this->munge->encode($key)};
        }
    }

    public function asTransient(): never
    {
        throw new MethodNotSupportedException("Structs don't support transients");
    }

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
            static fn (string $k): Keyword => TypeFactory::getInstance()->keyword($k),
            static::ALLOWED_KEYS,
        );
    }

    protected function validateKey(Keyword $key): string
    {
        if (in_array($key->getName(), static::ALLOWED_KEYS)) {
            return $this->munge->encode($key->getName());
        }

        $keyName = Printer::nonReadable()->print($key);
        $structName = static::class;
        throw new InvalidArgumentException(sprintf("This key '%s' is not allowed for struct %s", $keyName, $structName));
    }
}
