<?php

declare(strict_types=1);

namespace Phel\Api\Infrastructure\Daemon;

use Phel\Api\ApiFacade;

use Throwable;

/**
 * Long-running JSON-RPC daemon speaking newline-delimited JSON over a pair
 * of streams. Typically stdin/stdout for editor-side integration.
 *
 * Run via `./bin/phel api-daemon` (see ApiDaemonCommand).
 */
final class ApiDaemon
{
    private readonly JsonRpcFraming $framing;

    private readonly JsonRpcDispatcher $dispatcher;

    /** @var resource */
    private $input;

    /** @var resource */
    private $output;

    /**
     * @param resource|null $input
     * @param resource|null $output
     */
    public function __construct(
        ApiFacade $facade,
        $input = null,
        $output = null,
    ) {
        $this->framing = new JsonRpcFraming();
        $this->dispatcher = new JsonRpcDispatcher($facade);
        /** @var resource $in */
        $in = $input ?? STDIN;
        /** @var resource $out */
        $out = $output ?? STDOUT;
        $this->input = $in;
        $this->output = $out;
    }

    public function getDispatcher(): JsonRpcDispatcher
    {
        return $this->dispatcher;
    }

    public function run(int $maxIterations = 0): void
    {
        $iterations = 0;
        while (!feof($this->input)) {
            if ($maxIterations > 0 && $iterations >= $maxIterations) {
                return;
            }

            try {
                $message = $this->framing->readMessage($this->input);
            } catch (Throwable $e) {
                $this->framing->writeMessage($this->output, [
                    'id' => null,
                    'error' => ['code' => -32700, 'message' => $e->getMessage()],
                ]);
                ++$iterations;
                continue;
            }

            if ($message === null) {
                return;
            }

            if ($message === []) {
                ++$iterations;
                continue;
            }

            $response = $this->dispatcher->dispatch($message);
            $this->framing->writeMessage($this->output, $response);

            ++$iterations;
        }
    }

    public function closeStreams(): void
    {
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        @fclose($this->input);
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        @fclose($this->output);
    }
}
