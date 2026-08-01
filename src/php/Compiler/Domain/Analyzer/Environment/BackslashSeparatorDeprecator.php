<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Compiler\Domain\Deprecation\DeprecationWarnings;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function sprintf;
use function str_replace;
use function str_starts_with;

/**
 * Detects `\` used as the namespace separator (e.g. `phel\core/map`,
 * `\Phel\Lang\Foo`) so the codebase can migrate to Clojure-compatible dot
 * syntax ahead of the backslash form being removed.
 *
 * Detection only: the enabled gate, the bundled-stdlib suppression, the
 * per-`(file, symbol)` dedup, and the macro-expansion attribution all belong
 * to {@see DeprecationWarnings}, which is why this class is stateless.
 *
 * Scope of detection is intentionally narrow: only symbols that pass
 * through `SymbolResolver::resolve()` — call sites and qualified refs.
 * `ns`, `:require`, `:use`, and related forms are tracked as follow-ups
 * in https://github.com/phel-lang/phel-lang/issues/2827.
 *
 * @internal
 */
final class BackslashSeparatorDeprecator
{
    private static ?self $instance = null;

    /**
     * Shared instance. Stateless, so it exists only to spare the analyzer a
     * per-resolution allocation; there is nothing to reset between runs.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function maybeWarn(Symbol $symbol): void
    {
        $location = $symbol->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        $this->maybeWarnString($symbol->getFullName(), $location);
    }

    public function maybeWarnString(string $namespace, SourceLocation $location): void
    {
        // No `isEnabled()` short-circuit: this one deprecation announces
        // whether or not the flag is on, because it is the one already
        // scheduled for removal at the next major. `announceOnceAtOrigin()`
        // still applies the dedup and the first-party scoping.
        if (!$this->containsBackslashSeparator($namespace)) {
            return;
        }

        DeprecationWarnings::announceOnceAtOrigin(
            $location,
            $namespace,
            fn(string $file, int $line): string => $this->buildMessage($namespace, $file, $line),
        );
    }

    /**
     * True only when `\` separates two non-empty segments. A leading-only `\`
     * (PHP's global-namespace marker on top-level symbols like
     * `\JSON_UNESCAPED_SLASHES`, `\strlen`, `\DateTimeInterface`) is not a
     * Phel namespace separator and must not warn.
     */
    private function containsBackslashSeparator(string $fullName): bool
    {
        return str_contains(ltrim($fullName, '\\'), '\\');
    }

    private function buildMessage(string $original, string $file, int $line): string
    {
        return sprintf(
            "Backslash ('\\') namespace separator in symbol '%s' at %s:%d is deprecated; "
            . "use dot ('.') instead — e.g. '%s'. "
            . 'The backslash form will be removed in a future release.',
            $original,
            $file,
            $line,
            $this->suggestion($original),
        );
    }

    private function suggestion(string $original): string
    {
        // Drop the leading backslash from class FQNs and convert all
        // remaining `\` separators to `.` to match Clojure syntax.
        $trimmed = str_starts_with($original, '\\') ? substr($original, 1) : $original;

        return str_replace('\\', '.', $trimmed);
    }
}
