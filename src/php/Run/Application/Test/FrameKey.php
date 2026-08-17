<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

/**
 * Frame-field names shared between the orchestrator (parent) and the
 * worker subcommand. Single source of truth so wire-format drift can
 * only happen here.
 *
 * @internal
 */
final class FrameKey
{
    public const string INDEX = 'index';

    public const string NS = 'ns';

    public const string FILE = 'file';

    public const string OPTIONS = 'options';

    /** Ordered `{ns, file}` entries the worker evaluates before running the namespace. */
    public const string LOAD_ORDER = 'load-order';

    public const string TYPE = 'type';

    public const string OK = 'ok';

    public const string OUTPUT = 'output';

    public const string FAILED_TESTS = 'failed-tests';

    /** Whether the namespace's run was narrowed by a `^:focus` test. */
    public const string FOCUSED = 'focused';

    public const string ERROR = 'error';

    public const string COUNTS = 'counts';

    public const string COUNT_PASS = 'pass';

    public const string COUNT_FAILED = 'failed';

    public const string COUNT_ERROR = 'error';

    public const string COUNT_SKIPPED = 'skipped';

    public const string COUNT_TOTAL = 'total';

    public const string TYPE_RESULT = 'result';
}
