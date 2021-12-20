<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Struct;

use InvalidArgumentException;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;
use Phel\Lang\Collections\Exceptions\MethodNotSupportedException;
use Phel\Lang\Collections\Map\AbstractPersistentMap;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Map\TransientMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;
use Traversable;

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
            null
        );
        $this->munge = new Munge();
    }

    public function withMeta(?PersistentMapInterface $meta)
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
            yield TypeFactory::getInstance()->keyword($key) => $this->{$this->munge->encode($key)};
        }
    }

    /**
     * @return TransientMapInterface
     */
    public function asTransient()
    {
        throw new MethodNotSupportedException('Structs don\'t support transients');
    }

    public function equals($other): bool
    {
        if (!$other instanceof static) {
            return false;
        }

        return parent::equals($other);
    }

    /**
     * @param Keyword $key
     */
    protected function validateKey(Keyword $key): string
    {
        if (in_array($key->getName(), static::ALLOWED_KEYS)) {
            return $this->munge->encode($key->getName());
        }

        $keyName = Printer::nonReadable()->print($key);
        $structName = static::class;
        throw new InvalidArgumentException("This key '$keyName' is not allowed for struct $structName");
    }

    public function getAllowedKeys(): array
    {
        return array_map(
            fn (string $k): Keyword => TypeFactory::getInstance()->keyword($k),
            static::ALLOWED_KEYS
        );
    }
}
