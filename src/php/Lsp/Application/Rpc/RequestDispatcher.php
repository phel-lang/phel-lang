<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

use Phel\Lsp\Application\Session\Session;
use Phel\Lsp\Domain\HandlerInterface;
use Throwable;

use function is_array;
use function is_string;
use function sprintf;

/**
 * Routes a decoded LSP message to the correct handler and returns the
 * response payload (or null for notifications / requests that shouldn't
 * respond).
 */
final class RequestDispatcher
{
    /** @var array<string, HandlerInterface> */
    private array $handlers = [];

    public function __construct(
        private readonly ResponseBuilder $responses,
    ) {}

    public function register(HandlerInterface $handler): void
    {
        $this->handlers[$handler->method()] = $handler;
    }

    /**
     * @return list<string>
     */
    public function knownMethods(): array
    {
        return array_keys($this->handlers);
    }

    public function hasMethod(string $method): bool
    {
        return isset($this->handlers[$method]);
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|null
     */
    public function dispatch(array $message, Session $session): ?array
    {
        $id = $message['id'] ?? null;
        $method = is_string($message['method'] ?? null) ? $message['method'] : '';
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        /** @var array<string, mixed> $params */

        if ($method === '') {
            if ($id === null) {
                return null;
            }

            return $this->responses->error($id, ResponseBuilder::INVALID_REQUEST, 'Missing method.');
        }

        /** @psalm-suppress MixedArrayTypeCoercion */
        $handler = $this->handlers[$method] ?? null;
        if (!$handler instanceof HandlerInterface) {
            if ($id === null) {
                // Unknown notification: per LSP spec we silently ignore.
                return null;
            }

            return $this->responses->error(
                $id,
                ResponseBuilder::METHOD_NOT_FOUND,
                sprintf('Unknown method: %s', $method),
            );
        }

        try {
            $result = $handler->handle($params, $session);
        } catch (Throwable $throwable) {
            if ($id === null) {
                return null;
            }

            return $this->responses->error(
                $id,
                ResponseBuilder::INTERNAL_ERROR,
                $throwable->getMessage(),
            );
        }

        if ($handler->isNotification() || $id === null) {
            return null;
        }

        return $this->responses->result($id, $result);
    }
}
