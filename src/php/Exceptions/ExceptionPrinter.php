<?php

namespace Phel\Exceptions;

use Exception;
use Phel\CodeSnippet;
use Throwable;

interface ExceptionPrinter {

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void;

    public function printStackTrace(Throwable $e): void;
};