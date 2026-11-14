<?php

declare(strict_types=1);

namespace Phel\Run\Application\Test;

use Phel\Shared\ScalarCoercion;

use function htmlspecialchars;
use function is_array;
use function is_string;
use function sprintf;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * Decoded worker-to-parent result frame. Plain value object so the
 * orchestrator's dispatch loop can hand around a typed record instead
 * of raw `array<string, mixed>` dictionaries.
 *
 * @internal
 */
final readonly class WorkerResult
{
    /**
     * @param list<string> $failedTests
     */
    public function __construct(
        public int $index,
        public string $ns,
        public bool $ok,
        public string $output,
        public array $failedTests,
        public Counts $counts,
        public WorkerOutcome $outcome = WorkerOutcome::Verdict,
        public bool $focused = false,
        public string $junitXml = '',
    ) {}

    /**
     * @param array<string, mixed> $frame
     */
    public static function fromFrame(array $frame): self
    {
        $rawCounts = $frame[FrameKey::COUNTS] ?? [];
        if (!is_array($rawCounts)) {
            $rawCounts = [];
        }

        /** @var array<string, mixed> $rawCounts */
        return new self(
            ScalarCoercion::toInt($frame[FrameKey::INDEX] ?? null, -1),
            ScalarCoercion::toString($frame[FrameKey::NS] ?? null),
            (bool) ($frame[FrameKey::OK] ?? false),
            ScalarCoercion::toString($frame[FrameKey::OUTPUT] ?? null),
            self::extractStringList($frame[FrameKey::FAILED_TESTS] ?? null),
            Counts::fromArray($rawCounts),
            WorkerOutcome::fromFrameValue($frame[FrameKey::OUTCOME] ?? null),
            (bool) ($frame[FrameKey::FOCUSED] ?? false),
            ScalarCoercion::toString($frame[FrameKey::JUNIT_XML] ?? null),
        );
    }

    /**
     * Synthetic result for a worker that died before responding. Output
     * carries how it ended and what it left on its pipes.
     */
    public static function fromCrash(int $index, string $ns, string $report, string $exitStatus = ''): self
    {
        return new self(
            $index,
            $ns,
            false,
            sprintf("Worker died while running %s%s.\n%s", $ns, $exitStatus === '' ? '' : ' (' . $exitStatus . ')', $report),
            [],
            new Counts(error: 1, total: 1),
            WorkerOutcome::WorkerDied,
        );
    }

    /**
     * Synthetic result for a namespace the parent stopped at `--timeout`.
     * Carries its own JUnit document, since the worker never wrote one.
     */
    public static function fromTimeout(int $index, string $ns, int $seconds, string $lastTest): self
    {
        $message = sprintf(
            'Timed out after %ds running %s%s.',
            $seconds,
            $ns,
            $lastTest === '' ? '' : sprintf(' (last test started: %s)', $lastTest),
        );

        return new self(
            $index,
            $ns,
            false,
            '<error>' . $message . "</error>\n",
            $lastTest === '' ? [] : [$ns . '/' . $lastTest],
            new Counts(error: 1, total: 1),
            WorkerOutcome::TimedOut,
            junitXml: self::timeoutJunitXml($ns, $lastTest, $seconds, $message),
        );
    }

    /**
     * Whether re-running this namespace on a fresh worker can change the
     * answer. Only a worker-level fault qualifies; a compile error and a
     * failing test are verdicts about the source.
     */
    public function isRetryable(): bool
    {
        return $this->outcome->isRetryable();
    }

    private static function timeoutJunitXml(string $ns, string $lastTest, int $seconds, string $message): string
    {
        $attr = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
        $time = (string) $seconds;

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<testsuites tests="1" failures="0" errors="1" skipped="0" time="' . $time . '">'
            . '<testsuite name="' . $attr($ns) . '" tests="1" failures="0" errors="1" skipped="0" time="' . $time . '">'
            . '<testcase name="' . $attr($lastTest === '' ? $ns : $lastTest) . '" classname="' . $attr($ns) . '" file="" line="0">'
            . '<error message="' . $attr($message) . '" type="Timeout"></error>'
            . '</testcase></testsuite></testsuites>';
    }

    /**
     * @return list<string>
     */
    private static function extractStringList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entry) {
            if (is_string($entry) && $entry !== '') {
                $out[] = $entry;
            }
        }

        return $out;
    }
}
