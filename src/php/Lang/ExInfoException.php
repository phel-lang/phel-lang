<?php

declare(strict_types=1);

namespace Phel\Lang;

use Exception;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Throwable;

/**
 * Exception that carries a data map, matching Clojure's ex-info semantics.
 */
final class ExInfoException extends Exception
{
    public function __construct(
        string $message,
        private readonly PersistentMapInterface $data,
        ?Throwable $cause = null,
    ) {
        parent::__construct($message, 0, $cause);
    }

    public function getData(): PersistentMapInterface
    {
        return $this->data;
    }
}
