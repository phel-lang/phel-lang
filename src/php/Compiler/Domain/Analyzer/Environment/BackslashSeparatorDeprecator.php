<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer\Environment;

use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;

use function dirname;
use function in_array;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Emits `E_USER_DEPRECATED` warnings when user code uses `\` as the
 * namespace separator (e.g. `phel\core/map`, `\Phel\Lang\Foo`) so the
 * codebase can migrate to Clojure-compatible dot syntax ahead of the
 * backslash form being removed.
 *
 * Scope of detection is intentionally narrow: only symbols that pass
 * through `SymbolResolver::resolve()` — call sites and qualified refs.
 * `ns`, `:require`, `:use`, and related forms are tracked as follow-ups
 * in https://github.com/phel-lang/phel-lang/issues/1567.
 */
final class BackslashSeparatorDeprecator
{
    private static ?self $instance = null;

    /** @var array<string, true> */
    private array $seen = [];

    /** @var callable(string): void */
    private $emitter;

    public function __construct(
        private readonly bool $enabled,
        ?callable $emitter = null,
    ) {
        $this->emitter = $emitter ?? static function (string $msg): void {
            trigger_error($msg, E_USER_DEPRECATED);
        };
    }

    /**
     * Returns the process-wide deprecator, creating it lazily from the
     * `PHEL_WARN_DEPRECATIONS` env var so every detection site shares
     * the same dedup state across a compile run.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self(self::readEnvFlag());
    }

    /**
     * Replace the singleton with a preconfigured instance — used by tests
     * that need to assert on captured messages or flip the enabled flag.
     */
    public static function useInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    public static function enable(): void
    {
        self::$instance = new self(true);
    }

    /**
     * Drop the cached singleton so the next `getInstance()` call re-reads
     * the environment. Intended for test `tearDown()` hooks.
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function maybeWarn(Symbol $symbol): void
    {
        if (!$this->enabled) {
            return;
        }

        $location = $symbol->getStartLocation();
        if (!$location instanceof SourceLocation) {
            return;
        }

        $file = $location->getFile();
        if ($file === '' || $this->isPhelStdlibSource($file)) {
            return;
        }

        $original = $symbol->getFullName();
        if (!$this->containsBackslashSeparator($original)) {
            return;
        }

        $key = $file . '|' . $original;
        if (isset($this->seen[$key])) {
            return;
        }

        $this->seen[$key] = true;
        ($this->emitter)($this->buildMessage($original, $file, $location->getLine()));
    }

    public function maybeWarnString(string $namespace, SourceLocation $location): void
    {
        if (!$this->enabled) {
            return;
        }

        $file = $location->getFile();
        if ($file === '' || $this->isPhelStdlibSource($file)) {
            return;
        }

        if (!$this->containsBackslashSeparator($namespace)) {
            return;
        }

        $key = $file . '|' . $namespace;
        if (isset($this->seen[$key])) {
            return;
        }

        $this->seen[$key] = true;
        ($this->emitter)($this->buildMessage($namespace, $file, $location->getLine()));
    }

    private static function readEnvFlag(): bool
    {
        $flag = getenv('PHEL_WARN_DEPRECATIONS');

        return !in_array($flag, [false, '', '0'], true);
    }

    /**
     * Source-path suppression for phel's bundled stdlib. The path is
     * anchored to this package's own `src/phel`, so nested-layout user
     * projects with their own `src/phel` still receive warnings.
     */
    private function isPhelStdlibSource(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);
        $stdlibRoot = str_replace('\\', '/', dirname(__DIR__, 5) . '/phel');

        return $normalized === $stdlibRoot
            || str_starts_with($normalized, $stdlibRoot . '/');
    }

    private function containsBackslashSeparator(string $fullName): bool
    {
        return str_contains($fullName, '\\');
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
