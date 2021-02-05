<?php

declare(strict_types=1);

namespace Phel\Command\Test\Exceptions;

use RuntimeException;

final class CannotFindAnyTestsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Cannot find any tests');
    }
}
