<?php

declare(strict_types=1);

namespace Phel\Command\Domain\Exceptions;

use Phel\Command\Domain\Exceptions\Extractor\ReadModel\FilePosition;

/**
 * The frame a runtime error report points its `at` line at: where the failure
 * belongs in the source the user wrote, plus the compiled file and line it was
 * resolved from.
 *
 * @internal
 */
final readonly class AnchorFrame
{
    public function __construct(
        public FilePosition $position,
        public string $compiledFile,
        public int $compiledLine,
    ) {}

    /**
     * Code typed at the prompt has no file to open, only the prompt itself.
     */
    public function isReplInput(): bool
    {
        return EvaluatedCodeLocation::matches($this->position->filename());
    }

    /**
     * Whether a source map turned the compiled location back into a Phel one.
     */
    public function isMapped(): bool
    {
        return $this->position->filename() !== $this->compiledFile;
    }
}
