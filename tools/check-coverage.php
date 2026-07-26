<?php

declare(strict_types=1);

/**
 * Reads a Clover report and fails when line coverage falls below a floor.
 *
 * PHPUnit can produce the report but cannot gate on it, so the number was
 * measurable and never enforced. `docs/stability.md` asks for a published figure
 * and a gate on it; this is the gate.
 *
 * Usage: php tools/check-coverage.php <clover.xml> <minimum-percent>
 *
 * The floor is a ratchet, not a target. Raise it when the real figure moves
 * comfortably past it; never lower it to make a red build green.
 */

$cloverPath = $argv[1] ?? '';
$minimum = (float) ($argv[2] ?? '0');

if ($cloverPath === '' || !is_file($cloverPath)) {
    fwrite(STDERR, "Usage: php tools/check-coverage.php <clover.xml> <minimum-percent>\n");
    exit(2);
}

$xml = simplexml_load_file($cloverPath);

if ($xml === false) {
    fwrite(STDERR, \sprintf("Could not parse %s as XML.\n", $cloverPath));
    exit(2);
}

$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    fwrite(STDERR, "The Clover report has no <project><metrics> element.\n");
    exit(2);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];

if ($statements === 0) {
    fwrite(STDERR, "The Clover report contains no statements; the run produced no coverage.\n");
    exit(2);
}

$percent = $covered / $statements * 100;

printf("Line coverage: %.2f%% (%d/%d statements)\n", $percent, $covered, $statements);
printf("Required floor: %.2f%%\n", $minimum);

// The step summary is what makes the number *published* rather than merely
// measured: it lands on the run page without anyone downloading an artifact.
$summaryPath = getenv('GITHUB_STEP_SUMMARY');

if (\is_string($summaryPath) && $summaryPath !== '') {
    file_put_contents(
        $summaryPath,
        \sprintf(
            "### Coverage\n\n**%.2f%%** line coverage (%d/%d statements), floor %.2f%%.\n",
            $percent,
            $covered,
            $statements,
            $minimum,
        ),
        FILE_APPEND,
    );
}

if ($percent + 0.005 < $minimum) {
    fwrite(STDERR, \sprintf(
        "\nCoverage %.2f%% is below the %.2f%% floor.\n"
        . "Add tests for what this change touched. Lowering the floor is not the fix.\n",
        $percent,
        $minimum,
    ));
    exit(1);
}

echo "Coverage floor satisfied.\n";
