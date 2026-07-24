<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Exceptions;

use Exception;

use function get_debug_type;
use function sprintf;

/**
 * Thrown when a lazy sequence body produces a value that is not a sequence,
 * e.g. `(lazy-seq 5)`. Mirrors Clojure, which rejects the same expression with
 * "Don't know how to create ISeq from: java.lang.Long".
 */
final class NotASeqException extends Exception
{
    public static function forValue(mixed $value): self
    {
        return new self(sprintf("Don't know how to create a seq from: %s", get_debug_type($value)));
    }
}
