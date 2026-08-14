<?php

declare(strict_types=1);

namespace Phel\Lang\Collections\Exceptions;

use OutOfBoundsException;

/**
 * Extends SPL's `OutOfBoundsException` so that an index error is catchable the
 * same way however the call was compiled.
 *
 * `phel.core/nth` throws `\OutOfBoundsException` from its own body, but a `nth`
 * on a statically typed collection is lowered to `->get()`, which throws this.
 * Both spellings are the same error, and before this they shared no ancestor
 * but `Exception`, so `(try ... (catch \OutOfBoundsException e ...))` caught
 * one and not the other depending on whether the binding happened to carry a
 * tag. Widening the parent is additive: anything catching this class, or
 * `Exception`, still catches it.
 */
final class IndexOutOfBoundsException extends OutOfBoundsException {}
