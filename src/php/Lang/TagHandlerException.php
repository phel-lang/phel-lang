<?php

declare(strict_types=1);

namespace Phel\Lang;

use RuntimeException;

/**
 * Thrown by a tagged-literal handler when the input form is invalid.
 *
 * The reader catches this exception and rewraps it as a ReaderException
 * carrying the source location of the offending `#tag` literal.
 */
final class TagHandlerException extends RuntimeException {}
