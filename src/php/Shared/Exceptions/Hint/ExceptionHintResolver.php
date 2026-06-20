<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions\Hint;

use Throwable;

/**
 * Returns the first applicable actionable hint for an error, or null when none
 * of the registered hints match. The cause is unwrapped one level so wrapped
 * exceptions (e.g. compiled-code wrappers) still match on their root message.
 */
final readonly class ExceptionHintResolver
{
    /**
     * @param list<ExceptionHintInterface> $hints
     */
    public function __construct(
        private array $hints,
    ) {}

    public function hintFor(Throwable $e): ?string
    {
        foreach ([$e, $e->getPrevious()] as $candidate) {
            if (!$candidate instanceof Throwable) {
                continue;
            }

            foreach ($this->hints as $hint) {
                if ($hint->appliesTo($candidate)) {
                    return $hint->hint($candidate);
                }
            }
        }

        return null;
    }
}
