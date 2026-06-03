<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

use Exception;
use Phel\Lang\SourceLocation;
use RuntimeException;

abstract class AbstractLocatedException extends RuntimeException
{
    private ?ErrorCode $errorCode = null;

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

    public function getErrorCode(): ?ErrorCode
    {
        return $this->errorCode;
    }

    /**
     * Attaches a standardized error identifier to this exception. Subclasses
     * call this (typically from their constructor) to tag the located error
     * with its PHELxxx {@see ErrorCode} for reporting.
     */
    protected function setErrorCode(ErrorCode $errorCode): void
    {
        $this->errorCode = $errorCode;
    }
}
