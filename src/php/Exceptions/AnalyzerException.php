<?php

declare(strict_types=1);

namespace Phel\Exceptions;

use Exception;
use Phel\Lang\AbstractType;

final class AnalyzerException extends PhelCodeException
{
    public static function withLocation(string $message, AbstractType $type, ?Exception $nested = null): self
    {
        return new self(
            $message,
            $type->getStartLocation(),
            $type->getEndLocation(),
            $nested
        );
    }
}
