<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lint\Application\Config\RuleRegistry;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Api\Diagnostic;

use function count;
use function in_array;
use function is_string;
use function sprintf;

/**
 * Flags references to definitions marked `:deprecated`, either in the project
 * index or in this very file.
 *
 * The marker is the real `:deprecated` metadata the compiler already honours
 * (`DeprecatedDefinitionWarner`), never prose: a docstring that merely
 * *mentions* the word (`"... :deprecated, :min-arity ..."`, or a note about a
 * deprecated PHP builtin) says nothing about the definition it documents.
 *
 * The definition's own name is not a "use" of it, so the defining form is
 * skipped; otherwise deprecating something would flag its own declaration.
 */
final readonly class DiscouragedVarRule implements LintRuleInterface
{
    private const array DEFINING_FORMS = ['def', 'def-', 'defn', 'defn-', 'defmacro', 'defmacro-'];

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

        /** @var list<Diagnostic> $result */
        $result = [];
        foreach ($analysis->forms as $form) {
            $this->inspect($form, $discouraged, $analysis->uri, $result);
        }

        return $result;
    }

    /**
     * @param array<string, string> $discouraged
     * @param list<Diagnostic>      $result
     */
    private function inspect(mixed $form, array $discouraged, string $uri, array &$result): void
    {
        if ($form instanceof Symbol) {
            $this->reportSymbol($form, $discouraged, $uri, $result);

            return;
        }

        if ($form instanceof PersistentListInterface) {
            $skipIndex = $this->definitionNameIndex($form);
            $size = count($form);
            for ($i = 0; $i < $size; ++$i) {
                if ($i !== $skipIndex) {
                    $this->inspect($form->get($i), $discouraged, $uri, $result);
                }
            }

            return;
        }

        if ($form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                $this->inspect($child, $discouraged, $uri, $result);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $key => $value) {
                $this->inspect($key, $discouraged, $uri, $result);
                $this->inspect($value, $discouraged, $uri, $result);
            }
        }
    }

    /**
     * @param array<string, string> $discouraged
     * @param list<Diagnostic>      $result
     */
    private function reportSymbol(Symbol $node, array $discouraged, string $uri, array &$result): void
    {
        $name = $node->getName();
        if (!isset($discouraged[$name])) {
            return;
        }

        $result[] = DiagnosticBuilder::fromForm(
            $this->code(),
            sprintf("Use of discouraged var '%s' (%s).", $name, $discouraged[$name]),
            $uri,
            $node,
        );
    }

    /**
     * Index of the symbol a defining form declares, so it is not counted as a
     * use of itself. `null` when the form declares nothing.
     */
    private function definitionNameIndex(mixed $form): ?int
    {
        if (!$form instanceof PersistentListInterface || count($form) < 2) {
            return null;
        }

        $head = $form->get(0);
        if (!$head instanceof Symbol || !in_array($head->getName(), self::DEFINING_FORMS, true)) {
            return null;
        }

        return $form->get(1) instanceof Symbol ? 1 : null;
    }

    /**
     * @return array<string, string> symbolName => reason
     */
    private function collectDiscouraged(FileAnalysis $analysis): array
    {
        $map = [];
        foreach ($analysis->projectIndex->definitions as $def) {
            if ($def->isDeprecated()) {
                $map[$def->name] = sprintf('deprecated: %s', $def->deprecated);
            }
        }

        // The project index does not cover the file currently being linted.
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
        if ($this->definitionNameIndex($form) === null) {
            return;
        }

        /** @var PersistentListInterface<mixed> $form */
        $ident = $form->get(1);
        if (!$ident instanceof Symbol) {
            return;
        }

        $reason = $this->deprecationReason($ident->getMeta());
        $size = count($form);
        for ($i = 2; $i < $size && $reason === ''; ++$i) {
            $meta = $form->get($i);
            if ($meta instanceof PersistentVectorInterface) {
                break;
            }

            if ($meta instanceof PersistentMapInterface) {
                $reason = $this->deprecationReason($meta);
            }
        }

        if ($reason !== '') {
            $map[$ident->getName()] = sprintf('deprecated: %s', $reason);
        }
    }

    /**
     * @param PersistentMapInterface<mixed, mixed>|null $meta
     */
    private function deprecationReason(?PersistentMapInterface $meta): string
    {
        if (!$meta instanceof PersistentMapInterface) {
            return '';
        }

        $value = $meta->find(Keyword::create('deprecated'));
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $value === true ? 'deprecated' : '';
    }
}
