<?php

declare(strict_types=1);

namespace Phel\Lang\TagHandlers;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phel\Lang\TagHandlerException;

use function preg_match;
use function sprintf;

/**
 * Built-in handler for the `#inst "..."` tagged literal.
 *
 * Accepts an ISO 8601 timestamp string (RFC 3339 subset) and returns a
 * `\DateTimeImmutable`. Missing timezone offsets are interpreted as UTC.
 */
final readonly class InstTagHandler extends AbstractStringTagHandler
{
    /**
     * Matches ISO 8601 / RFC 3339 timestamps of the shape
     * `YYYY-MM-DDThh:mm:ss[.fff][Z|+hh:mm|-hh:mm]`.
     */
    private const string REGEX = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?$/';

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
        if (preg_match(self::REGEX, $form) !== 1) {
            throw new TagHandlerException(sprintf(
                '#inst value "%s" is not a valid ISO 8601 / RFC 3339 timestamp.',
                $form,
            ));
        }

        try {
            $hasOffset = preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/', $form) === 1;
            if ($hasOffset) {
                return new DateTimeImmutable($form);
            }

            return new DateTimeImmutable($form, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            throw new TagHandlerException(sprintf(
                '#inst value "%s" is not a valid ISO 8601 / RFC 3339 timestamp: %s',
                $form,
                $exception->getMessage(),
            ), 0, $exception);
        }
    }
}
