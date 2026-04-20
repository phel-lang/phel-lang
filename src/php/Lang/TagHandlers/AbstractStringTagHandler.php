<?php

declare(strict_types=1);

namespace Phel\Lang\TagHandlers;

use Phel\Lang\TagHandlerException;

use function is_string;
use function sprintf;

/**
 * Base class for tag handlers whose input form must be a string literal.
 *
 * Concrete subclasses implement {@see handleString()} and rely on this
 * class to reject non-string forms with a uniform error message.
 */
abstract readonly class AbstractStringTagHandler
{
    final public function __invoke(mixed $form): mixed
    {
        if (!is_string($form)) {
            throw new TagHandlerException(sprintf(
                '#%s expects a string literal%s.',
                $this->tagName(),
                $this->example() === '' ? '' : ' (e.g. ' . $this->example() . ')',
            ));
        }

        return $this->handleString($form);
    }

    /**
     * Tag identifier used in error messages (e.g. "inst", "regex", "uuid").
     */
    abstract protected function tagName(): string;

    /**
     * Short example shown in the "expects a string literal" error, or an
     * empty string to omit the parenthetical. Example:
     * `#uuid "00000000-0000-0000-0000-000000000000"`.
     */
    abstract protected function example(): string;

    abstract protected function handleString(string $form): mixed;
}
