<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use function json_encode;
use function ksort;
use function preg_match;
use function sprintf;
use function strlen;
use function strrpos;
use function substr;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const PREG_OFFSET_CAPTURE;

/**
 * Joins the JUnit XML documents `phel.test`'s `junit-xml` reporter wrote in
 * each worker, one per namespace, into the one document a serial run prints.
 * The `<testsuite>` elements are kept byte for byte, in namespace order, and
 * only the root totals are summed, so the reporter stays the one renderer.
 *
 * @internal
 */
final class JunitXmlReport
{
    /** The root element exactly as `phel.test`'s `junit-render` writes it. */
    private const string ROOT_PATTERN = '/<testsuites tests="(\d+)" failures="(\d+)" errors="(\d+)" skipped="(\d+)" time="([^"]*)">/';

    private const string ROOT_CLOSE = '</testsuites>';

    /** @var array<int, string> */
    private array $documentsByIndex = [];

    public function add(int $index, string $document): void
    {
        if ($document !== '') {
            $this->documentsByIndex[$index] = $document;
        }
    }

    public function render(): string
    {
        ksort($this->documentsByIndex);

        $tests = 0;
        $failures = 0;
        $errors = 0;
        $skipped = 0;
        $time = 0.0;
        $suites = '';
        foreach ($this->documentsByIndex as $document) {
            $closeAt = strrpos($document, self::ROOT_CLOSE);
            if ($closeAt === false || preg_match(self::ROOT_PATTERN, $document, $root, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $tests += (int) $root[1][0];
            $failures += (int) $root[2][0];
            $errors += (int) $root[3][0];
            $skipped += (int) $root[4][0];
            $time += (float) $root[5][0];

            $suitesAt = $root[0][1] + strlen($root[0][0]);
            $suites .= substr($document, $suitesAt, $closeAt - $suitesAt);
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?><testsuites tests="%d" failures="%d" errors="%d" skipped="%d" time="%s">%s%s',
            $tests,
            $failures,
            $errors,
            $skipped,
            json_encode($time, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
            $suites,
            self::ROOT_CLOSE,
        );
    }
}
