<?php

declare(strict_types=1);

namespace Phel\Lang;

use InvalidArgumentException;
use Phel\Printer;

abstract class Struct extends Table
{
    /**
     * Returns the list of allowed keywords.
     *
     * @return Keyword[]
     */
    abstract public function getAllowedKeys(): array;

    public function offsetSet($offset, $value): void
    {
        $this->validateOffset($offset);
        parent::offsetSet($offset, $value);
    }

    public function offsetExists($offset): bool
    {
        $this->validateOffset($offset);
        return parent::offsetExists($offset);
    }

    public function offsetUnset($offset): void
    {
        $this->validateOffset($offset);
        parent::offsetUnset($offset);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        $this->validateOffset($offset);
        return parent::offsetGet($offset);
    }

    /**
     * Asserts if the offset is a valid value.
     *
     * @param AbstractType|scalar|null $offset
     *
     * @throws InvalidArgumentException
     */
    protected function validateOffset($offset): void
    {
        if (!in_array($offset, $this->getAllowedKeys(), false)) {
            $keyName = Printer::nonReadable()->print($offset);
            $structName = static::class;
            throw new InvalidArgumentException(
                "This key '$keyName' is not allowed for struct $structName"
            );
        }
    }
}
