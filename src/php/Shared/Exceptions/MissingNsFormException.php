<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * A file whose first form is not `(ns ...)` is analysed outside any namespace,
 * so the first macro it calls fails to resolve. The analyzer's own error stays
 * in the previous slot, which is what the report headline and anchor show.
 */
final class MissingNsFormException extends RuntimeException
{
    private function __construct(
        private readonly string $path,
        Throwable $previous,
    ) {
        parent::__construct(
            sprintf('%s does not start with an (ns ...) form: %s', $path, $previous->getMessage()),
            0,
            $previous,
        );
    }

    public static function inFile(string $path, Throwable $previous): self
    {
        return new self($path, $previous);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
