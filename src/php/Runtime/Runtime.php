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
     * @param string $namespacePrefix
     * @param array<int, string> $paths
     */
    public function addPath(string $namespacePrefix, array $paths): void
    {
        /** @var array<int, string> $paths */
        $realpaths = array_map(fn ($p) => realpath($p), $paths);
        $length = strlen($namespacePrefix);
        if ('\\' !== $namespacePrefix[$length - 1]) {
            throw new InvalidArgumentException('A non-empty prefix must end with a namespace separator.');
        }

        if (isset($this->paths[$namespacePrefix])) {
            /** @psalm-suppress InvalidPropertyAssignmentValue */
            $this->paths[$namespacePrefix] = array_unique([...$this->paths[$namespacePrefix], ...$realpaths]);
        } else {
            $this->paths[$namespacePrefix] = $realpaths;
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
        return array_merge(...array_values($this->paths));
    }
}
