<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Op;

use function array_keys;
use function is_array;

/**
 * Routes a decoded message to the right OpHandlerInterface based on "op" key.
 * Unknown ops get an `unknown-op` status response.
 */
final class OpDispatcher
{
    /** @var array<string, OpHandlerInterface> */
    private array $handlers = [];

    /**
     * @param iterable<OpHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers = [])
    {
        foreach ($handlers as $handler) {
            $this->register($handler);
        }
    }

    public function register(OpHandlerInterface $handler): void
    {
        $this->handlers[$handler->name()] = $handler;
    }

    /**
     * @return list<string>
     */
    public function knownOps(): array
    {
        return array_keys($this->handlers);
    }

    /**
     * @param mixed $message decoded bencode value (expected to be an assoc array)
     *
     * @return list<OpResponse>
     */
    public function dispatch(mixed $message): array
    {
        if (!is_array($message)) {
            return [new OpResponse([
                'status' => [OpStatus::ERROR, OpStatus::INVALID_MESSAGE, OpStatus::DONE],
                'message' => 'Top-level nREPL message must be a dictionary.',
            ])];
        }

        /** @var array<string, mixed> $message */
        $request = OpRequest::fromMessage($message);

        if ($request->op === '') {
            return [OpResponse::build(
                $request->id,
                $request->session,
                ['message' => 'Missing "op" key in request.'],
                [OpStatus::ERROR, OpStatus::INVALID_OP, OpStatus::DONE],
            )];
        }

        $handler = $this->handlers[$request->op] ?? null;
        if (!$handler instanceof OpHandlerInterface) {
            return [OpResponse::build(
                $request->id,
                $request->session,
                ['message' => 'Unknown op: ' . $request->op],
                [OpStatus::ERROR, OpStatus::UNKNOWN_OP, OpStatus::DONE],
            )];
        }

        return $handler->handle($request);
    }
}
