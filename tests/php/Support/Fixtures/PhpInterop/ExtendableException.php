<?php

declare(strict_types=1);

namespace PhelTest\Support\Fixtures\PhpInterop;

use RuntimeException;

/**
 * A namespaced exception that is deliberately not `final`, so a Phel
 * `defexception` can extend it. Phel classes are `final` by convention, which
 * leaves nothing in `src/` to point a dotted-parent test at.
 */
class ExtendableException extends RuntimeException {}
