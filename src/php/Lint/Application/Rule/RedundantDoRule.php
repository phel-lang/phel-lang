<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;

use function count;

/**
 * Flags redundant `(do ...)` forms:
 *
 * - a `do` with zero or one body form (returns nil / a single value)
 * - a `do` that is the sole expression inside another implicit-do
 *   position (`defn` body, `let` body, `fn` body, `when`, `try`, etc.)
 */
final readonly class RedundantDoRule implements LintRuleInterface
{
    public function code(): string
    {
        return RuleRegistry::REDUNDANT_DO;
    }

    public function apply(FileAnalysis $analysis): array
    {
        /** @var list<Diagnostic> $result */
        $result = [];
        foreach ($analysis->forms as $form) {
            FormWalker::walk($form, function (mixed $node) use ($analysis, &$result): void {
                if (!$node instanceof PersistentListInterface || count($node) === 0) {
                    return;
                }

                $head = $node->get(0);
                if (!$head instanceof Symbol || $head->getName() !== Symbol::NAME_DO) {
                    return;
                }

                if ((count($node) - 1) <= 1) {
                    $result[] = DiagnosticBuilder::fromForm(
                        $this->code(),
                        'Redundant `do`: fewer than two body forms.',
                        $analysis->uri,
                        $node,
                    );
                }
            });
        }

        return $result;
    }
}
