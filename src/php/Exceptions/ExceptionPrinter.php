<?php

namespace Phel\Exceptions;

use Phel\Stream\CodeSnippet;

interface ExceptionPrinter {

    public function printException(PhelCodeException $e, CodeSnippet $codeSnippet);
};