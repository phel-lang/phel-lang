<?php

declare(strict_types=1);

namespace Phel\Transpiler\Domain\Analyzer\Exceptions;

use Exception;
use Phel\Lang\TypeInterface;
use Phel\Transpiler\Domain\Exceptions\AbstractLocatedException;

final class AnalyzerException extends AbstractLocatedException
{
    public static function withLocation(string $message, TypeInterface $type, ?Exception $nested = null): self
    {
        return new self(
            $message,
            $type->getStartLocation(),
            $type->getEndLocation(),
            $nested,
        );
    }
}
