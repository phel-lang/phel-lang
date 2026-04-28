<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

use Phel\Lsp\Application\Session\Session;
use Throwable;

use function feof;

/**
 * Owns the read/dispatch/write loop. Terminates when the client closes
 * the input stream or when `exit` triggers shutdown.
 */
final readonly class LspServer
{
    public function __construct(
        private MessageReader $reader,
        private MessageWriter $writer,
        private RequestDispatcher $dispatcher,
        private ResponseBuilder $responses,
        private Session $session,
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
            if (feof($input)) {
                return 0;
            }

            if ($maxIterations > 0 && $iterations >= $maxIterations) {
                return 0;
            }

            try {
                $message = $this->reader->read($input);
            } catch (Throwable $throwable) {
                $this->writer->write($output, $this->responses->error(
                    null,
                    ResponseBuilder::PARSE_ERROR,
                    $throwable->getMessage(),
                ));
                ++$iterations;
                continue;
            }

            if ($message === null) {
                return 0;
            }

            if ($message === []) {
                ++$iterations;
                continue;
            }

            $response = $this->dispatcher->dispatch($message, $this->session);
            if ($response !== null) {
                $this->writer->write($output, $response);
            }

            if ($this->session->shutdownRequested() && ($message['method'] ?? '') === 'exit') {
                return 0;
            }

            ++$iterations;
        }
    }
}
