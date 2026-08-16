<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Exception;

use RuntimeException;

/**
 * The unmutated test suite did not pass, so no mutant can be scored.
 *
 * @internal
 */
final class BaselineFailedException extends RuntimeException {}
