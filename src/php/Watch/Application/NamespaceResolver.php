<?php

declare(strict_types=1);

namespace Phel\Watch\Application;

use Phel\Watch\Domain\NamespaceResolverInterface;
use Throwable;

use function file_exists;
use function file_get_contents;
use function in_array;
use function is_string;
use function preg_match;
use function str_replace;
use function strlen;
use function trim;

/**
 * Extracts the fully-qualified Phel namespace from a source string or file.
 *
 * Uses a regex over the first balanced `ns` / `in-ns` form. The compiler's
 * NamespaceExtractor gives identical results but drags the full parse/read
 * pipeline into a watcher hot path; this lightweight resolver is enough to
 * decide which namespace changed.
 */
final class NamespaceResolver implements NamespaceResolverInterface
{
    private const string NS_FORM_REGEX = '/\(\s*(?:ns|in-ns)\s+\'?([A-Za-z0-9_\-*+!?\\\\.\/]+)/A';

    public function resolveFromFile(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if (!is_string($content) || $content === '') {
            return null;
        }

        return $this->resolveFromSource($content);
    }

    public function resolveFromSource(string $source): ?string
    {
        $code = $this->stripLeadingTrivia($source);
        if ($code === '') {
            return null;
        }

        try {
            $ok = preg_match(self::NS_FORM_REGEX, $code, $matches);
        } catch (Throwable) {
            return null;
        }

        if ($ok !== 1) {
            return null;
        }

        $raw = $matches[1];
        // Phel source uses either backslash or forward slash as namespace
        // separator; normalise to backslash for the Phel runtime.
        $normalised = str_replace('/', '\\', $raw);

        return trim($normalised);
    }

    /**
     * Drops line comments, whitespace, and shebang lines before the first
     * open-paren so the regex anchors on the very first form.
     */
    private function stripLeadingTrivia(string $source): string
    {
        $len = strlen($source);
        $i = 0;

        while ($i < $len) {
            $c = $source[$i];

            // Shebang or line comment.
            if ($c === '#' && $i + 1 < $len && $source[$i + 1] === '!') {
                $nl = strpos($source, "\n", $i);
                if ($nl === false) {
                    return '';
                }

                $i = $nl + 1;
                continue;
            }

            if ($c === ';') {
                $nl = strpos($source, "\n", $i);
                if ($nl === false) {
                    return '';
                }

                $i = $nl + 1;
                continue;
            }

            if (in_array($c, [' ', "\t", "\n", "\r"], true)) {
                ++$i;
                continue;
            }

            break;
        }

        return substr($source, $i);
    }
}
