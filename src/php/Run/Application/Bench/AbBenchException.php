<?php

declare(strict_types=1);

namespace Phel\Run\Application\Bench;

use RuntimeException;

/**
 * An A/B run that cannot go on: an unknown ref, a missing git, or a side
 * whose benchmark run failed. The message is written for the user as is.
 *
 * @internal
 */
final class AbBenchException extends RuntimeException {}
