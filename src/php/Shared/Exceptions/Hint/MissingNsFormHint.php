<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions\Hint;

use Phel\Shared\Exceptions\MissingNsFormException;
use Throwable;

use function sprintf;

final class MissingNsFormHint implements ExceptionHintInterface
{
    public function appliesTo(Throwable $e): bool
    {
        return $e instanceof MissingNsFormException;
    }

    public function hint(Throwable $e): string
    {
        $path = $e instanceof MissingNsFormException ? $e->getPath() : 'this file';

        return sprintf(
            "'%s' does not start with an (ns ...) form. Make (ns ...) the first form of the file.",
            $path,
        );
    }
}
