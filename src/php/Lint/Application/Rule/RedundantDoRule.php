<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
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
        $result = [];
        foreach ($analysis->forms as $form) {
            $this->walk($form, $analysis->uri, $result);
        }

        return $result;
    }

    /**
     * @param list<Diagnostic> $result
     */
    private function walk(mixed $form, string $uri, array &$result): void
    {
        if ($form instanceof PersistentListInterface && count($form) > 0) {
            $head = $form->get(0);
            if ($head instanceof Symbol && $head->getName() === Symbol::NAME_DO) {
                $bodyCount = count($form) - 1;
                if ($bodyCount <= 1) {
                    $result[] = DiagnosticBuilder::fromForm(
                        $this->code(),
                        'Redundant `do`: fewer than two body forms.',
                        $uri,
                        $form,
                    );
                }
            }

            foreach ($form as $child) {
                $this->walk($child, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                $this->walk($child, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                $this->walk($k, $uri, $result);
                $this->walk($v, $uri, $result);
            }
        }
    }
}
