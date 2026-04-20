<?php

declare(strict_types=1);

namespace Phel\Lang\TagHandlers;

use function preg_replace;

/**
 * Built-in handler for the `#regex "..."` tagged literal.
 *
 * Accepts a PCRE pattern (without delimiters) and returns a delimited
 * pattern string (`/pattern/`) equivalent to Phel's `#"..."` regex
 * literal. The value is directly usable with `re-find`, `re-seq`,
 * `re-matches`, and `re-pattern`.
 */
final readonly class RegexTagHandler extends AbstractStringTagHandler
{
    protected function tagName(): string
    {
        return 'regex';
    }

    protected function example(): string
    {
        return '#regex "[a-z]+"';
    }

    protected function handleString(string $form): string
    {
        // Match `RegexParser::parse()`: escape unescaped `/` so the
        // `/delimiter/` is never broken by the user's pattern.
        $pattern = preg_replace('/(?<!\\\\)\\//', '\\/', $form) ?? $form;

        return '/' . $pattern . '/';
    }
}
