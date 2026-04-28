<?php

declare(strict_types=1);

namespace Phel\Lang\TagHandlers;

use Phel\Lang\TagHandlerException;

use function preg_match;
use function sprintf;
use function strtolower;

/**
 * Built-in handler for the `#uuid "..."` tagged literal.
 *
 * Accepts a canonical UUID string (`8-4-4-4-12` hexadecimal groups) and
 * returns the lower-cased form. Phel has no dedicated UUID type, so the
 * reader value is a normalised string.
 */
final readonly class UuidTagHandler extends AbstractStringTagHandler
{
    private const string REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    protected function tagName(): string
    {
        return 'uuid';
    }

    protected function example(): string
    {
        return '#uuid "00000000-0000-0000-0000-000000000000"';
    }

    protected function handleString(string $form): string
    {
        if (preg_match(self::REGEX, $form) !== 1) {
            throw new TagHandlerException(sprintf(
                '#uuid value "%s" is not a canonical UUID string.',
                $form,
            ));
        }

        return strtolower($form);
    }
}
