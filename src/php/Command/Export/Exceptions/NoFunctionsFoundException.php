<?php

declare(strict_types=1);

namespace Phel\Command\Export\Exceptions;

use RuntimeException;

final class NoFunctionsFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No functions were found to be exported');
    }
}
