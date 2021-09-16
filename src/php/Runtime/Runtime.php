<?php

declare(strict_types=1);

namespace Phel\Runtime;

use InvalidArgumentException;
use Phel\Compiler\Analyzer\Environment\GlobalEnvironmentInterface;
use Phel\Compiler\Exceptions\CompilerException;
use Phel\Runtime\Exceptions\ExceptionPrinterInterface;
use Throwable;

class Runtime implements RuntimeInterface
{
    protected GlobalEnvironmentInterface $globalEnv;
    private ExceptionPrinterInterface $exceptionPrinter;

    /** @var array<string, array<int, string>> */
    private array $paths = [];

    public function __construct(
        GlobalEnvironmentInterface $globalEnv,
        ExceptionPrinterInterface $exceptionPrinter
    ) {
        set_exception_handler([$this, 'exceptionHandler']);
        $this->addPath('phel\\', [__DIR__ . '/../../phel']);

        $this->globalEnv = $globalEnv;
        $this->exceptionPrinter = $exceptionPrinter;
    }

    public function getEnv(): GlobalEnvironmentInterface
    {
        return $this->globalEnv;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * @param string $namespacePrefix
     * @param array<int, string> $path
     */
    public function addPath(string $namespacePrefix, array $path): void
    {
        /** @var array<int, string> $path */
        $path = array_map(fn ($p) => realpath($p), $path);
        $length = strlen($namespacePrefix);
        if ('\\' !== $namespacePrefix[$length - 1]) {
            throw new InvalidArgumentException('A non-empty prefix must end with a namespace separator.');
        }

        if (isset($this->paths[$namespacePrefix])) {
            $this->paths[$namespacePrefix] = array_unique([...$this->paths[$namespacePrefix], ...$path]);
        } else {
            $this->paths[$namespacePrefix] = $path;
        }
    }

    public function exceptionHandler(Throwable $exception): void
    {
        if ($exception instanceof CompilerException) {
            $this->exceptionPrinter->printException($exception->getNestedException(), $exception->getCodeSnippet());
        } else {
            $this->exceptionPrinter->printStackTrace($exception);
        }
    }

    protected function fileExists(string $filename): bool
    {
        return file_exists($filename);
    }

    public function getSourceDirectories(): array
    {
        return array_merge(...array_values($this->getPaths()));
    }

    public function loadNs(string $ns): bool
    {
        return true;
    }
}
