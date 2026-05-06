<?php

declare(strict_types=1);

namespace Phel\Lang\TagHandlers;

use InvalidArgumentException;
use Phel\Lang\TagHandlerException;
use Phel\Lang\Uuid;

use function sprintf;

/**
 * Built-in handler for the `#uuid "..."` tagged literal.
 *
 * Accepts a canonical UUID string (`8-4-4-4-12` hexadecimal groups) and
 * returns a {@see Uuid} value.
 */
final readonly class UuidTagHandler extends AbstractStringTagHandler
{
    protected function tagName(): string
    {
        return 'uuid';
    }

    protected function example(): string
    {
        return '#uuid "00000000-0000-0000-0000-000000000000"';
    }

    protected function handleString(string $form): Uuid
    {
        try {
            return Uuid::fromString($form);
        } catch (InvalidArgumentException) {
            throw new TagHandlerException(sprintf(
                '#uuid value "%s" is not a canonical UUID string.',
                $form,
            ));
        }
    }
}
