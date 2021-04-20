<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Struct;

use InvalidArgumentException;
use Phel\Compiler\Emitter\OutputEmitter\Munge;
use Phel\Compiler\Emitter\OutputEmitter\MungeInterface;
use Phel\Lang\Collections\HashMap\AbstractPersistentMap;
use Phel\Lang\Collections\HashMap\PersistentHashMapInterface;
use Phel\Lang\Collections\HashMap\TransientHashMapInterface;
use Phel\Lang\Keyword;
use Phel\Lang\TypeFactory;
use Phel\Printer\Printer;

/**
 * @template K
 * @template V
 *
 * @extends AbstractPersistentMap<K, V>
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

    public function withMeta(?PersistentHashMapInterface $meta)
    {
        $newInstance = clone $this;
        $newInstance->meta = $meta;
        return $newInstance;
    }

    public function containsKey($key): bool
    {
        return $key instanceof Keyword && in_array($key->getName(), static::ALLOWED_KEYS);
    }

    public function put($key, $value): PersistentHashMapInterface
    {
        $stringKey = $this->validateKey($key);

        $newInstance = clone $this;
        $newInstance->{$stringKey} = $value;
        return $newInstance;
    }

    public function remove($key): PersistentHashMapInterface
    {
        $stringKey = $this->validateKey($key);

        $newInstance = clone $this;
        $newInstance->{$stringKey} = null;
        return $newInstance;
    }

    public function count()
    {
        return count(static::ALLOWED_KEYS);
    }

    public function find($key)
    {
        $stringKey = $this->validateKey($key);
        return $this->{$stringKey};
    }

    public function getIterator()
    {
        foreach (static::ALLOWED_KEYS as $key) {
            yield TypeFactory::getInstance()->keyword($key) => $this->{$this->munge->encode($key)};
        }
    }

    /**
     * @return TransientHashMapInterface
     */
    public function asTransient()
    {
        throw new \RuntimeException('Structs don\'t support transients');
    }

    public function equals($other): bool
    {
        if (!$other instanceof static) {
            return false;
        }

        return parent::equals($other);
    }

    /**
     * @param K $key
     */
    protected function validateKey($key): string
    {
        if ($key instanceof Keyword && in_array($key->getName(), static::ALLOWED_KEYS)) {
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
