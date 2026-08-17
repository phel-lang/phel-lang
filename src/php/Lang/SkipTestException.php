<?php

declare(strict_types=1);

namespace Phel\Lang;

use RuntimeException;

/**
 * Thrown by `phel.test/skip!` to end the current test as skipped with a
 * reason; `phel.test` catches it around the test body and reports the
 * skip. Anything else that catches it should rethrow.
 */
final class SkipTestException extends RuntimeException {}
