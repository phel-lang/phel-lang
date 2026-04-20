<?php

declare(strict_types=1);

namespace Phel\Lsp\Application\Rpc;

/**
 * Builds JSON-RPC 2.0 response / notification payloads as PHP arrays.
 *
 * Separating construction from transport makes the dispatcher trivial to
 * unit-test without needing a real stream.
 */
final class ResponseBuilder
{
    public const int PARSE_ERROR = -32700;

    public const int INVALID_REQUEST = -32600;

    public const int METHOD_NOT_FOUND = -32601;

    public const int INVALID_PARAMS = -32602;

    public const int INTERNAL_ERROR = -32603;

    public const int SERVER_NOT_INITIALIZED = -32002;

    /**
     * @return array{jsonrpc: string, id: mixed, result: mixed}
     */
    public function result(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @return array{jsonrpc: string, id: mixed, error: array{code: int, message: string, data?: mixed}}
     */
    public function error(mixed $id, int $code, string $message, mixed $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{jsonrpc: string, method: string, params: array<string, mixed>}
     */
    public function notification(string $method, array $params): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];
    }
}
