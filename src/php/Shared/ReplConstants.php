<?php

declare(strict_types=1);

namespace Phel\Shared;

interface ReplConstants
{
    /**
     * Set only by `phel repl`, and only for what is specific to that prompt:
     * the `phel.repl` refers it injects into every namespace it analyses.
     * Anything that is true of interactive evaluation in general belongs on
     * {@see self::INTERACTIVE_MODE} instead.
     */
    public const string REPL_MODE = '*repl-mode*';

    /**
     * Set by every entry point that evaluates code a human typed rather than a
     * file on disk: `phel repl`, `phel eval` and the nREPL server. Re-defining
     * a symbol is normal there, so the duplicate-definition guard steps aside.
     */
    public const string INTERACTIVE_MODE = '*interactive-mode*';
}
