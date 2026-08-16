<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Exception;

use RuntimeException;

/**
 * The worker subprocess could not load the project or answer the baseline.
 *
 * @internal
 */
final class WorkerFailedException extends RuntimeException {}
