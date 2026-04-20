<?php

declare(strict_types=1);

namespace Phel\Nrepl\Application\Op;

use Phel\Nrepl\Domain\Op\OpHandlerInterface;
use Phel\Nrepl\Domain\Op\OpRequest;
use Phel\Nrepl\Domain\Op\OpResponse;
use Phel\Nrepl\Domain\Op\OpStatus;

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
        return [OpResponse::build(
            $request->id,
            $request->session,
            [],
            [OpStatus::DONE, OpStatus::SESSION_IDLE],
        )];
    }
}
