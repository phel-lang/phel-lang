<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Op;

use function is_string;

/**
 * Read-only view over a decoded nREPL message.
 *
 * @internal
 */
final readonly class OpRequest
{
    /**
     * @param string               $op      the op name from the nREPL message
     * @param ?string              $id      optional message id used to route responses back to the caller
     * @param ?string              $session optional session id; when null the request applies globally
     * @param array<string, mixed> $raw     the raw decoded message; prefer stringParam() over direct access
     */
    public function __construct(
        public string $op,
        public ?string $id,
        public ?string $session,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $message
     */
    public static function fromMessage(array $message): self
    {
        $op = isset($message['op']) && is_string($message['op']) ? $message['op'] : '';
        $id = isset($message['id']) && is_string($message['id']) ? $message['id'] : null;
        $session = isset($message['session']) && is_string($message['session']) ? $message['session'] : null;

        return new self($op, $id, $session, $message);
    }

    public function stringParam(string $key, string $default = ''): string
    {
        return $this->optionalStringParam($key) ?? $default;
    }

    /**
     * The param as a string, or null when it is absent or not a string.
     * Use this over {@see self::stringParam()} when "absent" and "empty"
     * are different cases — the `eval` op answers a missing `code` param
     * with `no-code` but treats empty code as a no-op.
     */
    public function optionalStringParam(string $key): ?string
    {
        $value = $this->raw[$key] ?? null;
        return is_string($value) ? $value : null;
    }
}
