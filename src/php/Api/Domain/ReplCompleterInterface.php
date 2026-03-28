<?php

declare(strict_types=1);

namespace Phel\Api\Domain;

use Phel\Api\Transfer\CompletionResultTransfer;

interface ReplCompleterInterface
{
    /**
     * @return list<string>
     */
    public function complete(string $input): array;

    /**
     * Complete input with type annotations for nREPL clients.
     *
     * @return list<CompletionResultTransfer>
     */
    public function completeWithTypes(string $input): array;
}
