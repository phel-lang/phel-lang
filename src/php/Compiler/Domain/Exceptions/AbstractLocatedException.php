<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Exceptions;

use Exception;
use Phel\Lang\SourceLocation;
use RuntimeException;

abstract class AbstractLocatedException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?SourceLocation $startLocation = null,
        private readonly ?SourceLocation $endLocation = null,
        ?Exception $nestedException = null,
    ) {
        parent::__construct($message, 0, $nestedException);
    }

    public function getStartLocation(): ?SourceLocation
    {
        return $this->startLocation;
    }

    public function getEndLocation(): ?SourceLocation
    {
        return $this->endLocation;
    }
}
