<?php

declare(strict_types=1);

namespace Phel\Run\Domain\Test;

use InvalidArgumentException;

/**
 * Thrown by `phel.test` before any test runs, once the test namespaces that
 * could register the reporter have loaded.
 *
 * @internal
 */
final class UnknownReporterException extends InvalidArgumentException {}
