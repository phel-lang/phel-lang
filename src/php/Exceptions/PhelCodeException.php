<?php

namespace Phel\Exceptions;

use Exception;
use Phel\Lang\SourceLocation;

class PhelCodeException extends Exception
{

    /**
     * @var ?SourceLocation
     */
    private $startLocation;

    /**
     * @var ?SourceLocation
     */
    private $endLocation;

    public function __construct(string $message, ?SourceLocation $startLocation = null, ?SourceLocation $endLocation = null, ?Exception $nestedException = null)
    {
        parent::__construct($message, 0, $nestedException);
        $this->startLocation = $startLocation;
        $this->endLocation = $endLocation;
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
