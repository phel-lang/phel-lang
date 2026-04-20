<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Op;

use function is_string;

/**
 * Read-only view over a decoded nREPL message.
 */
final readonly class OpRequest
{
    /**
     * @param array<string, mixed> $raw
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
        $value = $this->raw[$key] ?? null;
        return is_string($value) ? $value : $default;
    }
}
