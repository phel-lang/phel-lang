<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Handler;

use Phel\Command\Domain\Shared\Exceptions\ExceptionPrinterInterface;
use Phel\Transpiler\Domain\Exceptions\CompilerException;
use Throwable;

final class ExceptionHandler
{
    private const ERROR_TYPES = [
        E_ALL => 'E_ALL',
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
    ];

    private static string $previousTextError = '';

    public function __construct(
        private readonly ExceptionPrinterInterface $exceptionPrinter,
    ) {
    }

    public function register(): void
    {
        $this->registerExceptionHandler();
        $this->registerCustomErrorHandler();
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

    private function registerCustomErrorHandler(): void
    {
        set_error_handler(function (
            int $errno,
            string $errstr,
            string $errfile = '',
            int $errline = 0,
            array $errcontext = [],
        ): bool {
            $errorNumber = error_reporting();
            $text = sprintf(
                "[%s] Error %s(%d) found!\nmessage: \"%s\"\nfile: %s:%d\ncontext: %s",
                date(DATE_ATOM),
                self::ERROR_TYPES[$errorNumber] ?? 'Unknown',
                $errorNumber,
                $errstr,
                $errfile,
                $errline,
                json_encode([...$errcontext, 'errno' => $errno], JSON_THROW_ON_ERROR),
            );

            if (self::$previousTextError !== $text) {
                $this->exceptionPrinter->printError($text);
            } else {
                $this->exceptionPrinter->printError('and again');
            }

            self::$previousTextError = $text;
            return true;
        }, E_ALL);
    }
}
