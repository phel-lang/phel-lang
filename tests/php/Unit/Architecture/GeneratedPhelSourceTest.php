<?php

declare(strict_types=1);

namespace PhelTest\Unit\Architecture;

use PHPUnit\Framework\TestCase;

use function implode;
use function is_array;
use function preg_match_all;
use function sprintf;
use function token_get_all;

/**
 * Phel source that phel itself generates and evaluates must spell namespaces
 * with `.`, never `\`.
 *
 * `DeprecationWarnings` suppresses the separator notice for the bundled stdlib
 * by *source path*, but a form built in PHP and handed to `eval()` has no such
 * path, so it is reported as if the user had written it. The user cannot act on
 * it: the offending text lives in phel's own PHP. `phel test`, the nREPL
 * `reload` / `run-tests` ops and the watch reload hooks all did this, so a run
 * with `--warn-deprecations` buried real notices under phel's own.
 *
 * Scoped to `src/php` on purpose. `\` is still supported (ADR 0008) and test
 * fixtures exercise it deliberately, `test-dir-accepts-dot-form` among them, so
 * a repository-wide rule would fail on the coverage that keeps the separator
 * honest.
 */
final class GeneratedPhelSourceTest extends TestCase
{
    use ScansPhpSourcesTrait;

    /**
     * A bundled `phel*` namespace whose segments are joined with `\`.
     *
     * Anchored to the reserved `phel` prefix, in lowercase, because that is what
     * tells generated Phel apart from a PHP FQN: `phel\test/run-tests` is Phel
     * source, `Phel\Lang\Keyword` is a class name that legitimately appears in
     * emitted PHP. Matching one or two backslashes covers both quoting styles,
     * since a single-quoted PHP literal carries `phel\test` and a double-quoted
     * one carries `phel\\test`.
     *
     * The trailing `/` is deliberately *not* required. A var reference spells it
     * (`phel\test/run-tests`), but a `(:require phel\repl ...)` inside an
     * evaluated `ns` form does not, and that form warns just the same.
     *
     * The segment needs two characters or more, so an escape sequence in a
     * double-quoted literal is not read as a namespace: the regex in
     * `DocstringSignatureParser` matches a fenced ```` ```phel\n ```` block, and
     * every real bundled segment (`core`, `test`, `repl`, ...) is longer.
     */
    private const string BACKSLASH_NAMESPACE = '#\bphel(?:-[a-z0-9]+)?\\\\{1,2}[a-z][a-z0-9-]+#';

    public function test_generated_phel_source_uses_dotted_namespaces(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn('src/php') as $relativePath => $contents) {
            foreach ($this->stringLiteralsOf($contents) as [$line, $literal]) {
                if (preg_match_all(self::BACKSLASH_NAMESPACE, $literal, $matches) === 0) {
                    continue;
                }

                $offenders[] = sprintf('src/php/%s:%d uses %s', $relativePath, $line, implode(', ', $matches[0]));
            }
        }

        self::assertSame([], $offenders, 'Generated Phel source must use `.` as the namespace separator.');
    }

    /**
     * Only string literals, so the separator stays legal in prose: a docblock
     * naming `phel\repl/reload!` documents what the op used to emit and is not
     * itself evaluated.
     *
     * @return list<array{int, string}> [line, raw literal including quotes]
     */
    private function stringLiteralsOf(string $contents): array
    {
        $literals = [];

        foreach (token_get_all($contents) as $token) {
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] !== T_CONSTANT_ENCAPSED_STRING && $token[0] !== T_ENCAPSED_AND_WHITESPACE) {
                continue;
            }

            $literals[] = [$token[2], $token[1]];
        }

        return $literals;
    }
}
