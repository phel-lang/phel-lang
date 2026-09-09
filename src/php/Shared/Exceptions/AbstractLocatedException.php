<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

use Phel\Lang\SourceLocation;
use RuntimeException;
use Throwable;

abstract class AbstractLocatedException extends RuntimeException
{
    private ?ErrorCode $errorCode = null;

    private ?string $relatedLocationNote = null;

    public function __construct(
        string $message,
        private readonly ?SourceLocation $startLocation = null,
        private readonly ?SourceLocation $endLocation = null,
        ?Throwable $nestedException = null,
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
     * A second source position worth naming next to the error, already
     * formatted for the reader (e.g. `first defined at foo.phel:2`). The
     * printer renders it under the code snippet.
     */
    public function getRelatedLocationNote(): ?string
    {
        return $this->relatedLocationNote;
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

    protected function setRelatedLocationNote(string $relatedLocationNote): void
    {
        $this->relatedLocationNote = $relatedLocationNote;
    }
}
