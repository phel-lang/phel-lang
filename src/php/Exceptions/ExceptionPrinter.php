<?php

namespace Phel\Exceptions;

use Exception;
use Phel\Stream\CodeSnippet;

interface ExceptionPrinter {

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet): void;

    public function printStackTrace(Exception $e): void;
};