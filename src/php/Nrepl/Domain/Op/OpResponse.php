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

    /**
     * Build a response for the given request, carrying the routing
     * metadata (id/session) forwards automatically.
     *
     * @param array<string, mixed> $body
     * @param list<string>         $status
     */
    public static function forRequest(OpRequest $request, array $body = [], array $status = []): self
    {
        return self::build($request->id, $request->session, $body, $status);
    }

    /**
     * Final response frame carrying only the DONE status token.
     */
    public static function done(OpRequest $request): self
    {
        return self::build($request->id, $request->session, [], [OpStatus::DONE]);
    }

    /**
     * Convenience for an error response terminated by DONE.
     *
     * @param list<string> $extraStatus status tokens inserted between ERROR and DONE
     */
    public static function errorDone(OpRequest $request, string $message, array $extraStatus = []): self
    {
        return self::build(
            $request->id,
            $request->session,
            ['message' => $message],
            [OpStatus::ERROR, ...$extraStatus, OpStatus::DONE],
        );
    }

    public function withExtra(array $extra): self
    {
        return new self(array_merge($this->payload, $extra));
    }
}
