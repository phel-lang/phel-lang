<?php

declare(strict_types=1);

namespace Phel\Shared;

interface BuildConstants
{
    public const string BUILD_MODE = '*build-mode*';

    /**
     * Set while an `ns` form loads the files its `(:require ...)` clauses
     * resolve to. It suppresses the source-directory scan in the required
     * file's own `ns` form, which would otherwise recurse.
     *
     * Deliberately not `*build-mode*`, which it used to borrow. Build mode
     * additionally licenses the emitter to pin global call sites into
     * `static $__phel_call_N` slots, on the contract that redefinitions are
     * not expected. Loading a dependency at runtime does not meet that
     * contract: the resulting artifact ignored every later `with-redefs`, and
     * was written to the ordinary cache for later processes to reuse (#3015).
     */
    public const string LOADING_DEPENDENCIES = '*loading-dependencies*';
}
