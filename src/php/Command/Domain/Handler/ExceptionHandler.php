<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Handler;

use Phel\Command\Domain\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Compiler\Domain\Exceptions\CompilerException;
use Throwable;

final class ExceptionHandler
{
    private static string $previousNotice = '';

    public function __construct(
        private ExceptionPrinterInterface $exceptionPrinter,
    ) {
    }

    public function register(): void
    {
        $this->registerExceptionHandler();
        $this->registerNoticeHandler();
    }

    private function registerExceptionHandler(): void
    {
        set_exception_handler(function (Throwable $exception): void {
            if ($exception instanceof CompilerException) {
                $this->exceptionPrinter->printException($exception->getNestedException(), $exception->getCodeSnippet());
            } else {
                $this->exceptionPrinter->printStackTrace($exception);
            }
        });
    }

    private function registerNoticeHandler(): void
    {
        set_error_handler(function (
            int $errno,
            string $errstr,
            string $errfile = '',
            int $errline = 0,
            array $errcontext = [],
        ): bool {
            $text = sprintf(
                "[%s] NOTICE found!\nmessage: \"%s\"\nfile: %s:%d\ncontext: %s",
                date(DATE_ATOM),
                $errstr,
                $errfile,
                $errline,
                json_encode(array_merge($errcontext, ['errno' => $errno])),
            );
            if (self::$previousNotice !== $text) {
                $this->exceptionPrinter->printError($text);
            } else {
                $this->exceptionPrinter->printError('and again');
            }

            self::$previousNotice = $text;
            return true;
        }, E_NOTICE);
    }
}
