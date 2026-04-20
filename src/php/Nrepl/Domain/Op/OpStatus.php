<?php

declare(strict_types=1);

namespace Phel\Nrepl\Domain\Op;

/**
 * nREPL status tokens used in the `status` field of responses.
 *
 * Centralising these tokens prevents typos and drift between handlers
 * — every op can reuse the same constants for the final response frames.
 */
final class OpStatus
{
    public const string DONE = 'done';

    public const string ERROR = 'error';

    public const string UNKNOWN_OP = 'unknown-op';

    public const string INVALID_OP = 'invalid-op';

    public const string INVALID_MESSAGE = 'invalid-message';

    public const string EVAL_ERROR = 'eval-error';

    public const string LOAD_FILE_ERROR = 'load-file-error';

    public const string INCOMPLETE = 'incomplete';

    public const string NO_INFO = 'no-info';

    public const string SESSION_CLOSED = 'session-closed';

    public const string UNKNOWN_SESSION = 'unknown-session';

    public const string SESSION_IDLE = 'session-idle';
}
