<?php

declare(strict_types=1);

namespace Phel\Compiler\Domain\Analyzer;

use Phel\Shared\CompilerConstants;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_keys;
use function dirname;
use function file_get_contents;
use function is_dir;
use function ksort;
use function preg_match;
use function preg_match_all;
use function str_contains;

use const PREG_SET_ORDER;

/**
 * The public names each bundled `phel.*` namespace defines, read from the
 * stdlib sources rather than by loading them: an unresolved-symbol error has
 * to name the namespace to require without paying for every namespace it
 * could be in. A top-level `def` form is what counts, so a name a macro
 * generates is missed, which only costs a suggestion.
 *
 * @internal
 */
final class BundledSymbolIndex
{
    private const string NS_PATTERN = '/^\(ns\s+([^\s()]+)/m';

    private const string DEF_PATTERN = '/^\((?:def|defn|defmacro|defmulti|defstruct|definterface|defprotocol|defexception|defenum)\s+((?:\^\S+\s+)*)([^\s()\[\]{}"^]+)/m';

    /** @var array<string, array<string, array<string, true>>> */
    private static array $cache = [];

    private readonly string $stdlibDir;

    public function __construct(?string $stdlibDir = null)
    {
        $this->stdlibDir = $stdlibDir ?? dirname(__DIR__, 4) . '/phel';
    }

    /**
     * @return list<string>
     */
    public function namespaces(): array
    {
        return array_keys($this->index());
    }

    /**
     * @return list<string>
     */
    public function namesIn(string $namespace): array
    {
        return array_keys($this->index()[$namespace] ?? []);
    }

    /**
     * @return list<string>
     */
    public function namespacesDefining(string $name): array
    {
        $namespaces = [];
        foreach ($this->index() as $namespace => $names) {
            if (isset($names[$name])) {
                $namespaces[] = $namespace;
            }
        }

        return $namespaces;
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function index(): array
    {
        return self::$cache[$this->stdlibDir] ??= $this->build();
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function build(): array
    {
        if (!is_dir($this->stdlibDir)) {
            return [];
        }

        $index = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->stdlibDir));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'phel') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            // `phel.core` is loaded before any user code, so a name it
            // defines never reaches an unresolved-symbol error.
            if (preg_match(self::NS_PATTERN, $source, $ns) !== 1 || $ns[1] === CompilerConstants::PHEL_CORE_NAMESPACE) {
                continue;
            }

            $index[$ns[1]] ??= [];
            preg_match_all(self::DEF_PATTERN, $source, $defs, PREG_SET_ORDER);
            foreach ($defs as [, $meta, $name]) {
                if (!str_contains($meta, ':private')) {
                    $index[$ns[1]][$name] = true;
                }
            }
        }

        ksort($index);

        return $index;
    }
}
