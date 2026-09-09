<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions;

use Phel\Shared\Parser\ReadModel\CodeSnippet;
use Throwable;

interface ExceptionPrinterInterface
{
    public function getExceptionString(AbstractLocatedException $e, CodeSnippet $codeSnippet): string;

    /**
     * Called from Phel through PHP interop by `phel.test` (`(php/-> printer
     * (printStackTrace exception))`), so it has no PHP-side call site to grep
     * for. Do not remove as unused.
     */
    public function printStackTrace(Throwable $e): void;

    public function getStackTraceString(Throwable $e): string;

    /**
     * Trace limited to frames originating in Phel code, mapped back to their
     * `.phel` source locations; PHP-native frames are collapsed into a marker
     * naming the flag that expands them.
     *
     * `$showInternalFrames` is what `--stack-trace` turns on: every frame is
     * rendered, nothing is collapsed.
     */
    public function getUserFacingTraceString(Throwable $e, bool $showInternalFrames = false): string;
}
