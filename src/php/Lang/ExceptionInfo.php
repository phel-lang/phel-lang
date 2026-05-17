<?php

declare(strict_types=1);

namespace Phel\Lang;

use Exception;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Throwable;

final class ExceptionInfo extends Exception
{
    /**
     * @param PersistentMapInterface<mixed, mixed> $data
     */
    public function __construct(
        string $message,
        private readonly PersistentMapInterface $data,
        ?Throwable $cause = null,
    ) {
        parent::__construct($message, 0, $cause);
    }

    /**
     * @return PersistentMapInterface<mixed, mixed>
     */
    public function getData(): PersistentMapInterface
    {
        return $this->data;
    }
}
