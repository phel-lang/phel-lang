<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Runner;

use RuntimeException;
use Throwable;

use function implode;
use function sprintf;

/**
 * Thrown when `data-readers.phel` files were found but the `phel.reader`
 * namespace they depend on could not be resolved.
 *
 * Loading data readers is opt-in: nothing happens when no `data-readers.phel`
 * exists. Once one does, the user asked for those `(register-tag ...)` calls,
 * so failing to bootstrap the reader must not leave the tags quietly
 * unregistered and surface much later as an unreadable literal.
 */
final class DataReadersLoadException extends RuntimeException
{
    /**
     * @param list<string> $dataReaderFiles
     */
    public static function cannotBootstrapReader(array $dataReaderFiles, Throwable $previous): self
    {
        return new self(
            sprintf(
                "Cannot load the 'phel.reader' namespace required by %s: %s",
                implode(', ', $dataReaderFiles),
                $previous->getMessage(),
            ),
            0,
            $previous,
        );
    }
}
