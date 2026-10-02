<?php

declare(strict_types=1);

namespace Phel\Build\Domain\Extractor;

use Phel\Lang\SourceLocation;
use Phel\Shared\Exceptions\AbstractLocatedException;
use Phel\Shared\Exceptions\ErrorCode;

/**
 * A `(:require ...)` that resolves to nothing, located on the required
 * namespace in the requiring file's `ns` form.
 *
 * @internal
 */
final class MissingNamespaceException extends AbstractLocatedException
{
    public static function at(
        string $message,
        SourceLocation $start,
        SourceLocation $end,
        string $searchedNote,
    ): self {
        $e = new self($message, $start, $end);
        $e->setErrorCode(ErrorCode::MISSING_NAMESPACE);
        if ($searchedNote !== '') {
            $e->setRelatedLocationNote($searchedNote);
        }

        return $e;
    }
}
