<?php

declare(strict_types=1);

namespace Phel\Run\Application;

use function array_intersect;
use function array_values;
use function file_get_contents;
use function preg_match_all;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Selects bundled `phel.*` namespaces that file runs must seed before
 * evaluating an ad-hoc script.
 */
final readonly class BundledNamespaceDetector
{
    private const string CLOJURE_PREFIX = 'clojure.';

    /**
     * Matches fully qualified bundled references in the source: `phel.X/sym`
     * or the legacy `phel\X/sym`. The trailing slash anchors the match to
     * actual call/var references rather than comments containing the prefix.
     */
    private const string FQN_PATTERN = '#\bphel[.\\\\][A-Za-z0-9_\-.\\\\]+/#u';

    private const string PHEL_PREFIX = 'phel.';

    public function __construct(
        private BundledNamespaces $bundledNamespaces,
    ) {}

    /**
     * Returns the subset of bundled namespaces that the script references via
     * fully qualified `phel.X/sym` form. Returns an empty list when the script
     * contains no such reference, allowing callers to skip the bundled
     * dependency walk entirely.
     *
     * @return list<string>
     */
    public function detect(string $filename): array
    {
        $source = @file_get_contents($filename);
        if ($source === false || $source === '') {
            return [];
        }

        if (preg_match_all(self::FQN_PATTERN, $source, $matches) === false) {
            return [];
        }

        if ($matches[0] === []) {
            return [];
        }

        $referenced = [];
        foreach ($matches[0] as $token) {
            $ns = str_replace('\\', '.', substr($token, 0, -1));
            $referenced[$ns] = true;
        }

        return $this->intersectBundled($referenced);
    }

    /**
     * Returns bundled `phel.*` namespaces matching Clojure-compatible
     * dependencies such as `clojure.test`.
     *
     * @param list<string> $dependencies
     *
     * @return list<string>
     */
    public function remapClojureDependencies(array $dependencies): array
    {
        $candidates = [];
        foreach ($dependencies as $dependency) {
            $normalized = str_replace('\\', '.', $dependency);
            if (str_starts_with($normalized, self::CLOJURE_PREFIX)) {
                $candidates[self::PHEL_PREFIX . substr($normalized, strlen(self::CLOJURE_PREFIX))] = true;
            }
        }

        if ($candidates === []) {
            return [];
        }

        return $this->intersectBundled($candidates);
    }

    /**
     * @param array<string, true> $candidates
     *
     * @return list<string>
     */
    private function intersectBundled(array $candidates): array
    {
        $bundled = $this->bundledNamespaces->all();
        if ($bundled === []) {
            return [];
        }

        return array_values(array_intersect($bundled, array_keys($candidates)));
    }
}
