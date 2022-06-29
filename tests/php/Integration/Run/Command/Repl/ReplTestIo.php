<?php

declare(strict_types=1);

namespace PhelTest\Integration\Run\Command\Repl;

use Phel\Command\Domain\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Compiler\Domain\Exceptions\AbstractLocatedException;
use Phel\Compiler\Domain\Parser\ReadModel\CodeSnippet;
use Phel\Run\Domain\Repl\ReplCommandIoInterface;
use Throwable;

use function array_slice;
use function count;

final class ReplTestIo implements ReplCommandIoInterface
{
    /** @var list<string> */
    private array $outputs = [];

    /** @var list<InputLine> */
    private array $inputs = [];

    private int $currentIndex = 0;


    public function __construct(private ExceptionPrinterInterface $exceptionPrinter)
    {
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
            ++$this->currentIndex;

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
     * @return list<string>
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
