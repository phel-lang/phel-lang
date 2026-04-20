<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Api\Transfer\Diagnostic;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;

use function count;
use function in_array;
use function is_string;
use function sprintf;

/**
 * Flags references to definitions marked `^:deprecated` or
 * `^:no-doc` in the project index. The index is built by the upstream
 * `ApiFacade::indexProject` call; we just consume its definitions.
 *
 * In v1 the compiler does not yet record arbitrary user metadata on
 * definitions, so this rule acts on definitions whose *docstring or
 * name* match a small, stable deprecated-marker vocabulary:
 *   - name starts with `!deprecated-`
 *   - docstring mentions `Deprecated:` or `DEPRECATED`
 * Future passes can extend this without editing the rule signature.
 */
final readonly class DiscouragedVarRule implements LintRuleInterface
{
    public function code(): string
    {
        return RuleRegistry::DISCOURAGED_VAR;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $discouraged = $this->collectDiscouraged($analysis);
        if ($discouraged === []) {
            return [];
        }

        $result = [];
        foreach ($analysis->forms as $form) {
            $this->walk($form, $discouraged, $analysis->uri, $result);
        }

        return $result;
    }

    /**
     * @return array<string, string> symbolName => reason
     */
    private function collectDiscouraged(FileAnalysis $analysis): array
    {
        $map = [];
        foreach ($analysis->projectIndex->definitions as $def) {
            $name = $def->name;
            $docstring = $def->docstring;

            if (str_starts_with($name, '!deprecated-')) {
                $map[$name] = 'deprecated by name';

                continue;
            }

            if (preg_match('/\bdeprecated\b/i', $docstring) === 1) {
                $map[$name] = 'marked deprecated in docstring';
            }
        }

        // Also scan local forms for `^{:deprecated true}` metadata.
        foreach ($analysis->forms as $form) {
            $this->collectLocalDeprecations($form, $map);
        }

        return $map;
    }

    /**
     * @param array<string, string> $map
     */
    private function collectLocalDeprecations(mixed $form, array &$map): void
    {
        if (!$form instanceof PersistentListInterface || count($form) < 3) {
            return;
        }

        $head = $form->get(0);
        if (!$head instanceof Symbol) {
            return;
        }

        $name = $head->getName();
        if (!in_array($name, ['def', 'defn', 'defn-', 'defmacro', 'defmacro-'], true)) {
            return;
        }

        $ident = $form->get(1);
        if (!$ident instanceof Symbol) {
            return;
        }

        $size = count($form);
        for ($i = 2; $i < $size; ++$i) {
            $meta = $form->get($i);
            if ($meta instanceof PersistentMapInterface) {
                $value = $meta->find(Keyword::create('deprecated'));
                if ($value === true || (is_string($value) && $value !== '')) {
                    $map[$ident->getName()] = 'marked deprecated';
                }
            } elseif ($meta instanceof PersistentVectorInterface) {
                break;
            }
        }
    }

    /**
     * @param array<string, string> $discouraged
     * @param list<Diagnostic>      $result
     */
    private function walk(mixed $form, array $discouraged, string $uri, array &$result): void
    {
        if ($form instanceof Symbol) {
            $name = $form->getName();
            if (isset($discouraged[$name])) {
                $result[] = DiagnosticBuilder::fromForm(
                    $this->code(),
                    sprintf("Use of discouraged var '%s' (%s).", $name, $discouraged[$name]),
                    $uri,
                    $form,
                );
            }

            return;
        }

        if ($form instanceof PersistentListInterface || $form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                $this->walk($child, $discouraged, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                $this->walk($k, $discouraged, $uri, $result);
                $this->walk($v, $discouraged, $uri, $result);
            }
        }
    }
}
