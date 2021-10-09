<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Phel\Command\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Compiler\Exceptions\AbstractLocatedException;
use Phel\Compiler\Parser\ReadModel\CodeSnippet;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Throwable;

final class ReplTestIo implements ReplCommandIoInterface
{
    private ExceptionPrinterInterface $exceptionPrinter;

    /** @var array string[] */
    private array $outputs = [];

    /** @var InputLine[] */
    private array $inputs = [];

    private int $currentIndex = 0;


    public function __construct(ExceptionPrinterInterface $exceptionPrinter)
    {
        $this->exceptionPrinter = $exceptionPrinter;
    }

    public function readHistory(): void
    {
    }

    public function addHistory(string $line): void
    {
    }

    public function readline(?string $prompt = null): ?string
    {
        if ($this->currentIndex < count($this->inputs)) {
            $inputLine = $this->inputs[$this->currentIndex];
            $this->writeln($inputLine->__toString() . PHP_EOL);
            $this->currentIndex++;

            if ($inputLine->isCtrlD()) {
                return null;
            }

            return $inputLine->getContent();
        }

        return null;
    }

    public function writeStackTrace(Throwable $e): void
    {
        $this->write($this->exceptionPrinter->getStackTraceString($e));
    }

    public function writeLocatedException(AbstractLocatedException $e, CodeSnippet $codeSnippet): void
    {
        $this->write($this->exceptionPrinter->getExceptionString($e, $codeSnippet));
    }

    public function write(string $string = ''): void
    {
        $this->outputs[] = $string;
    }

    public function writeln(string $string = ''): void
    {
        $this->outputs[] = $string;
    }

    public function setInputs(InputLine ...$inputs): void
    {
        $this->inputs = $inputs;
        $this->currentIndex = 0;
    }

    /**
     * @return string[]
     */
    public function getOutputs(): array
    {
        return array_slice($this->outputs, 2, -1);
    }

    public function getOutputString(): string
    {
        return implode('', $this->getOutputs()) . PHP_EOL;
    }

    public function isBracketedPasteSupported(): bool
    {
        return false;
    }
}
