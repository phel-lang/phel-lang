<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Op;

use function array_merge;

/**
 * A single nREPL response frame. A handler may emit several of these before
 * the final one tagged with ["done"] in status.
 */
final readonly class OpResponse
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public array $payload) {}

    /**
     * Convenience constructor merging routing metadata onto the payload.
     *
     * @param array<string, mixed> $body
     * @param list<string>         $status
     */
    public static function build(
        ?string $id,
        ?string $session,
        array $body,
        array $status = [],
    ): self {
        $payload = $body;
        if ($id !== null && $id !== '') {
            $payload['id'] = $id;
        }

        if ($session !== null && $session !== '') {
            $payload['session'] = $session;
        }

        if ($status !== []) {
            $payload['status'] = $status;
        }

        return new self($payload);
    }

    public function withExtra(array $extra): self
    {
        return new self(array_merge($this->payload, $extra));
    }
}
