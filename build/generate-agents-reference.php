<?php

declare(strict_types=1);

/**
 * Generates .agents/reference/core.md from live Phel core meta.
 *
 * Source of truth: :doc / :example / :see-also metadata on every public
 * defn in src/phel/core/*.phel. Run from the repo root:
 *
 *   php build/generate-agents-reference.php
 *   composer docs-agents-reference
 *
 * CI runs this in validate-agents.sh and fails if output differs from
 * the committed file, guaranteeing the reference never drifts.
 */

use Gacela\Framework\Gacela;
use Phel\Api\ApiFacade;
use Phel\Api\Transfer\PhelFunction;
use Phel\Phel;

$root = \dirname(__DIR__);
require $root . '/vendor/autoload.php';

Phel::bootstrap($root);

$api = Gacela::getRequired(ApiFacade::class);
/** @var list<PhelFunction> $all */
$all = $api->getPhelFunctions(['phel\\core']);

$fns = array_values(array_filter(
    $all,
    static fn(PhelFunction $fn): bool => $fn->namespace === 'core',
));

usort(
    $fns,
    static fn(PhelFunction $a, PhelFunction $b): int => strcmp($a->name, $b->name),
);

$generated = gmdate('Y-m-d');
$total = \count($fns);

$out = <<<MD
# Phel Core Reference

Auto-generated from `:doc` / `:example` / `:see-also` metadata on public
`defn` forms in `src/phel/core/*.phel`. Do not edit by hand.

- Run `composer docs-agents-reference` to regenerate.
- `composer test-agents` fails if this file drifts from the source.

Generated: {$generated} UTC — {$total} public core functions.


MD;

$out .= "## Index\n\n";
foreach ($fns as $fn) {
    $anchor = toAnchor($fn->name);
    $out .= \sprintf("- [`%s`](#%s)\n", escapeInline($fn->name), $anchor);
}
$out .= "\n";

foreach ($fns as $fn) {
    $out .= renderFunction($fn);
}

$target = $root . '/.agents/reference/core.md';
if (!is_dir(\dirname($target))) {
    mkdir(\dirname($target), 0o755, true);
}

file_put_contents($target, $out);

fwrite(STDOUT, \sprintf("Wrote %d functions to %s\n", $total, $target));

function renderFunction(PhelFunction $fn): string
{
    $heading = \sprintf("## `%s`\n\n", escapeInline($fn->name));

    $body = '';
    foreach ($fn->signatures as $sig) {
        $body .= "```phel\n" . $sig . "\n```\n\n";
    }

    $desc = trim($fn->description);
    if ($desc !== '') {
        $body .= $desc . "\n\n";
    }

    $example = extractMeta($fn, 'example');
    if ($example !== '') {
        $body .= "**Example**\n\n```phel\n" . $example . "\n```\n\n";
    }

    $seeAlso = extractSeeAlso($fn);
    if ($seeAlso !== []) {
        $links = array_map(
            static fn(string $name): string => \sprintf('[`%s`](#%s)', escapeInline($name), toAnchor($name)),
            $seeAlso,
        );
        $body .= '**See also:** ' . implode(', ', $links) . "\n\n";
    }

    if ($fn->githubUrl !== '') {
        $body .= \sprintf("[source](%s)\n\n", $fn->githubUrl);
    }

    return $heading . $body;
}

function extractMeta(PhelFunction $fn, string $key): string
{
    $value = $fn->meta[$key] ?? '';
    if (\is_string($value)) {
        return trim($value);
    }

    return '';
}

/**
 * @return list<string>
 */
function extractSeeAlso(PhelFunction $fn): array
{
    $raw = $fn->meta['see-also'] ?? null;
    if ($raw === null) {
        return [];
    }

    $items = [];
    if (is_iterable($raw)) {
        foreach ($raw as $item) {
            if (\is_string($item) && $item !== '') {
                $items[] = $item;
            }
        }
    } elseif (\is_string($raw) && $raw !== '') {
        $items[] = $raw;
    }

    return array_values(array_unique($items));
}

function toAnchor(string $name): string
{
    $anchor = strtolower($name);
    $anchor = preg_replace('/[^a-z0-9\-]+/', '-', $anchor) ?? '';
    return trim($anchor, '-');
}

function escapeInline(string $value): string
{
    return str_replace('|', '\\|', $value);
}
