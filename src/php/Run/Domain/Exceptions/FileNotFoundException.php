<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Exceptions;

use RuntimeException;

final class FileNotFoundException extends RuntimeException
{
    public function __construct(string $file)
    {
        parent::__construct(sprintf('File "%s" not found', $file));
    }
}
