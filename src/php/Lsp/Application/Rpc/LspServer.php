<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

use JsonException;
use Phel\Lsp\Application\Diagnostics\DiagnosticPublisher;
use Phel\Lsp\Application\Session\Session;
use Throwable;

/**
 * Owns the read/dispatch/write loop. Terminates when the client closes
 * the input stream or when `exit` triggers shutdown.
 *
 * @internal
 */
final readonly class LspServer
{
    public function __construct(
        private MessageReader $reader,
        private MessageWriter $writer,
        private RequestDispatcher $dispatcher,
        private ResponseBuilder $responses,
        private Session $session,
        private ?DiagnosticPublisher $publisher = null,
    ) {}

    /**
     * @param resource $input
     * @param resource $output
     *
     * @return int process exit code
     */
    public function serve($input, $output, int $maxIterations = 0): int
    {
        $iterations = 0;
        while (true) {
            if ($maxIterations > 0 && $iterations >= $maxIterations) {
                return 0;
            }

            try {
                $this->publisher?->publishDue();
            } catch (Throwable) {
                return 1;
            }

            $message = $this->readMessage($input, $output);
            if ($message === false) {
                return 1;
            }

            if ($message === null) {
                return 0;
            }

            if ($message === []) {
                ++$iterations;
                continue;
            }

            $response = $this->dispatcher->dispatch($message, $this->session);
            if ($response !== null && !$this->writeResponse($output, $response)) {
                return 1;
            }

            if ($this->session->shutdownRequested() && ($message['method'] ?? '') === 'exit') {
                return 0;
            }

            ++$iterations;
        }
    }

    /**
     * @param resource $input
     * @param resource $output
     *
     * @return array<string, mixed>|false|null false when the parse-error response cannot be written
     */
    private function readMessage($input, $output): array|false|null
    {
        try {
            return $this->reader->read($input);
        } catch (Throwable $throwable) {
            return $this->writeResponse($output, $this->responses->error(
                null,
                ResponseBuilder::PARSE_ERROR,
                $throwable->getMessage(),
            )) ? [] : false;
        }
    }

    /**
     * @param resource             $output
     * @param array<string, mixed> $response
     */
    private function writeResponse($output, array $response): bool
    {
        try {
            try {
                $this->writer->write($output, $response);
            } catch (JsonException) {
                $this->writer->write($output, $this->responses->error(
                    $response['id'] ?? null,
                    ResponseBuilder::INTERNAL_ERROR,
                    'Could not encode response',
                ));
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }

}
