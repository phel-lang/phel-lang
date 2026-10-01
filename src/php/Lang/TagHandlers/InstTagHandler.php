<?php

declare(strict_types=1);

namespace Phel\Lang\TagHandlers;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phel\Lang\TagHandlerException;

use function checkdate;
use function preg_match;
use function sprintf;
use function substr;

/**
 * Built-in handler for the `#inst "..."` tagged literal.
 *
 * Accepts an ISO 8601 timestamp string (RFC 3339 subset, any prefix such as
 * `2026-03`) and returns a `\DateTimeImmutable`. Missing fields take their
 * minimum; a missing timezone offset is interpreted as UTC.
 */
final readonly class InstTagHandler extends AbstractStringTagHandler
{
    /** Matches every prefix of `YYYY-MM-DDThh:mm:ss[.fff][Z|+hh:mm|-hh:mm]`, as Clojure's reader does. */
    private const string REGEX = '/^(\d{4})(?:-(\d{2})(?:-(\d{2})(?:T(\d{2})(?::(\d{2})(?::(\d{2})(?:\.(\d+))?)?)?)?)?)?(?:(Z)|([-+])(\d{2}):?(\d{2}))?$/';

    protected function tagName(): string
    {
        return 'inst';
    }

    protected function example(): string
    {
        return '#inst "2026-04-20T12:00:00Z"';
    }

    protected function handleString(string $form): DateTimeImmutable
    {
        if (preg_match(self::REGEX, $form, $fields) !== 1) {
            throw $this->invalid($form);
        }

        $year = (int) $fields[1];
        $month = $this->field($fields, 2, 1);
        $day = $this->field($fields, 3, 1);
        $hour = $this->field($fields, 4, 0);
        $minute = $this->field($fields, 5, 0);
        $second = $this->field($fields, 6, 0);
        $offsetHour = $this->field($fields, 10, 0);
        $offsetMinute = $this->field($fields, 11, 0);

        // DateTimeImmutable rolls an out-of-range field over instead of failing.
        if (!checkdate($month, $day, $year)
            || $hour > 23 || $minute > 59 || $second > 59
            || $offsetHour > 23 || $offsetMinute > 59
        ) {
            throw $this->invalid($form);
        }

        $timestamp = sprintf('%04d-%02d-%02dT%02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        if (($fields[7] ?? '') !== '') {
            $timestamp .= '.' . substr($fields[7], 0, 6);
        }

        try {
            if (($fields[9] ?? '') !== '') {
                return new DateTimeImmutable(sprintf('%s%s%02d:%02d', $timestamp, $fields[9], $offsetHour, $offsetMinute));
            }

            return new DateTimeImmutable($timestamp, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            throw new TagHandlerException(sprintf(
                '#inst value "%s" is not a valid ISO 8601 / RFC 3339 timestamp: %s',
                $form,
                $exception->getMessage(),
            ), 0, $exception);
        }
    }

    private function invalid(string $form): TagHandlerException
    {
        return new TagHandlerException(sprintf(
            '#inst value "%s" is not a valid ISO 8601 / RFC 3339 timestamp.',
            $form,
        ));
    }

    /**
     * @param array<int, string> $fields
     */
    private function field(array $fields, int $group, int $default): int
    {
        $value = $fields[$group] ?? '';

        return $value === '' ? $default : (int) $value;
    }
}
