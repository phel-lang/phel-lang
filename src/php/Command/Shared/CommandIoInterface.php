<?php

declare(strict_types=1);

namespace Phel\Command\Shared;

use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Throwable;

interface CommandIoInterface
{
    public function createDirectory(string $directory): void;

    public function fileGetContents(string $filename): string;

    public function filePutContents(string $filename, string $content): void;

    public function writeStackTrace(Throwable $e): void;

    public function writeLocatedException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void;

    public function writeln(string $string): void;
}
