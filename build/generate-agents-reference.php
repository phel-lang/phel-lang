<?php

declare(strict_types=1);

/**
 * Generates .agents/reference/core.md from live Phel core meta.
 *
 * Source of truth: :doc / :example / :see-also metadata on every public
 * defn in src/phel/core/*.phel. Run from the repo root:
 *
 *   composer docs-agents-reference
 *
 * Output is deterministic (no timestamps), so validate-agents.sh can
 * regenerate and fail the build on any diff.
 */

use Gacela\Framework\Gacela;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\PhelFunction;
use Phel\Phel;

$root = \dirname(__DIR__);
require $root . '/vendor/autoload.php';

Phel::bootstrap($root);

/** @var list<PhelFunction> $fns */
$fns = array_values(array_filter(
    Gacela::getRequired(ApiFacade::class)->getPhelFunctions(['phel\\core']),
    static fn(PhelFunction $fn): bool => $fn->namespace === 'core',
));

usort(
    $fns,
    static fn(PhelFunction $a, PhelFunction $b): int => strcmp($a->name, $b->name),
);

$target = $root . '/.agents/reference/core.md';
if (!is_dir(\dirname($target))) {
    mkdir(\dirname($target), 0o755, true);
}

file_put_contents($target, renderReference($fns));
fwrite(STDOUT, \sprintf("Wrote %d functions to %s\n", \count($fns), $target));

/**
 * @param list<PhelFunction> $fns
 */
function renderReference(array $fns): string
{
    $header = <<<MD
# Phel Core Reference

Auto-generated from `:doc` / `:example` / `:see-also` metadata on public
`defn` forms in `src/phel/core/*.phel`. Do not edit by hand.

- Run `composer docs-agents-reference` to regenerate.
- `composer test-agents` fails when this file drifts from the source.


MD;

    $body = '';
    foreach ($fns as $fn) {
        $body .= renderFunction($fn);
    }

    return $header . $body;
}

function renderFunction(PhelFunction $fn): string
{
    $out = \sprintf("## `%s`\n\n", $fn->name);

    foreach ($fn->signatures as $sig) {
        $out .= "```phel\n" . $sig . "\n```\n\n";
    }

    $desc = trim($fn->description);
    if ($desc !== '') {
        $out .= $desc . "\n\n";
    }

    $example = $fn->meta['example'] ?? '';
    if (\is_string($example) && trim($example) !== '') {
        $out .= "**Example**\n\n```phel\n" . trim($example) . "\n```\n\n";
    }

    $seeAlso = extractSeeAlso($fn);
    if ($seeAlso !== []) {
        $items = array_map(static fn(string $name): string => '`' . $name . '`', $seeAlso);
        $out .= '**See also:** ' . implode(', ', $items) . "\n\n";
    }

    if ($fn->githubUrl !== '') {
        $out .= \sprintf("[source](%s)\n\n", $fn->githubUrl);
    }

    return $out;
}

/**
 * @return list<string>
 */
function extractSeeAlso(PhelFunction $fn): array
{
    $raw = $fn->meta['see-also'] ?? null;
    if (!is_iterable($raw)) {
        return [];
    }

    $items = [];
    foreach ($raw as $item) {
        if (\is_string($item) && $item !== '') {
            $items[] = $item;
        }
    }

    return array_values(array_unique($items));
}
