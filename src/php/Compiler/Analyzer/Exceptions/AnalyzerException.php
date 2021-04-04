<?php

declare(strict_types=1);

namespace Phel\Compiler\Analyzer\Exceptions;

use Exception;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Lang\TypeInterface;

final class AnalyzerException extends AbstractLocatedException
{
    public static function withLocation(string $message, TypeInterface $type, ?Exception $nested = null): self
    {
        return new self(
            $message,
            $type->getStartLocation(),
            $type->getEndLocation(),
            $nested
        );
    }
}
