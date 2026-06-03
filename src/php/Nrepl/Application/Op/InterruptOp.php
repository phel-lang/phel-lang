<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;

/**
 * Interrupt op (no-op stub). Phel evaluates synchronously, so there is no
 * background work to interrupt; this handler simply acknowledges the op to
 * keep editors happy.
 */
final class InterruptOp implements OpHandlerInterface
{
    public function name(): string
    {
        return 'interrupt';
    }

    public function handle(OpRequest $request): array
    {
        // Phel evaluates synchronously; there is nothing to interrupt on the
        // single-threaded request path. Acknowledge so editors stay happy.
        return [OpResponse::forRequest(
            $request,
            [],
            [OpStatus::DONE, OpStatus::SESSION_IDLE],
        )];
    }
}
