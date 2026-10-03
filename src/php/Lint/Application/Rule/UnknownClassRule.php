<?php

declare(strict_types=1);

namespace Phel\Lint\Application\Rule;

use Composer\Autoload\ClassLoader;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Keyword;
use Phel\Lang\Symbol;
use Phel\Lint\Domain\FileAnalysis;
use Phel\Lint\Domain\LintRuleInterface;
use Phel\Shared\Api\Diagnostic;
use Phel\Shared\Exceptions\Hint\ClassNotFoundHint;
use Phel\Shared\LintRuleCodes;

use function class_exists;
use function count;
use function enum_exists;
use function in_array;
use function interface_exists;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function trait_exists;

/**
 * Flags a static call `(Foo/bar ...)` whose class cannot be autoloaded, which
 * is what code ported from Clojure does with `Integer/parseInt` or
 * `Math/abs`. A warning, not an error: a class can still be loaded at runtime
 * before the call is reached.
 *
 * @internal
 */
final readonly class UnknownClassRule implements LintRuleInterface
{
    /**
     * Forms that name a class this file declares, so it exists only once the
     * file has run.
     */
    private const array CLASS_DEFINING_FORMS = ['defstruct', 'definterface', 'defexception', 'defenum', 'defrecord', 'deftype', 'defprotocol'];

    public function __construct(
        private ClassNotFoundHint $classNotFoundHint,
    ) {}

    public function code(): string
    {
        return LintRuleCodes::UNKNOWN_CLASS;
    }

    public function apply(FileAnalysis $analysis): array
    {
        $nsForm = NamespaceForm::find($analysis->forms);
        $aliases = $this->aliases($analysis->forms, $nsForm);
        $declared = $this->declaredClasses($analysis->forms);

        $result = [];
        foreach ($analysis->forms as $form) {
            if ($form === $nsForm) {
                continue;
            }

            FormWalker::walk($form, function (mixed $value) use ($aliases, $declared, $analysis, &$result): ?bool {
                if (!$value instanceof PersistentListInterface || count($value) === 0) {
                    return null;
                }

                $head = $value->get(0);
                if ($head instanceof Symbol && $head->getName() === Symbol::NAME_QUOTE) {
                    return false;
                }

                if ($head instanceof Symbol) {
                    $diagnostic = $this->check($head, $aliases, $declared, $analysis->uri);
                    if ($diagnostic instanceof Diagnostic) {
                        $result[] = $diagnostic;
                    }
                }

                return null;
            });
        }

        return $result;
    }

    /**
     * @param array{use: array<string, string>, require: array<string, true>} $aliases
     * @param array<string, true>                                             $declared
     */
    private function check(Symbol $head, array $aliases, array $declared, string $uri): ?Diagnostic
    {
        $namespace = $head->getNamespace();
        if ($namespace === null || isset($aliases['require'][$namespace]) || isset($declared[$namespace])) {
            return null;
        }

        // Only a class reference: `\Foo`, `\vendor\Foo`, `Foo` or `Foo.Bar`.
        // Without a leading `\`, a lowercase namespace is a Phel namespace,
        // which `phel/unresolved-symbol` covers.
        if (preg_match('/^(\\\\[A-Za-z_][\w\\\\]*|[A-Z][\w.\\\\]*)$/', $namespace) !== 1) {
            return null;
        }

        $class = str_starts_with($namespace, '\\')
            ? ltrim($namespace, '\\')
            : ($aliases['use'][$namespace] ?? str_replace('.', '\\', $namespace));

        if ($this->isLoadable($class)) {
            return null;
        }

        $message = sprintf("Class '%s' in '%s' cannot be autoloaded.", $class, $head->getFullName());
        $javaClassHint = $this->classNotFoundHint->javaClassHint($class);
        if ($javaClassHint !== null) {
            $message .= ' ' . $javaClassHint;
        }

        return DiagnosticBuilder::fromForm($this->code(), $message, $uri, $head);
    }

    /**
     * Lint must not run project code, so no autoloader is invoked: a class is
     * loadable when it is already declared, or when a Composer loader knows a
     * file for it. A class only a custom autoloader can find is reported,
     * which a warning tolerates.
     */
    private function isLoadable(string $class): bool
    {
        if (class_exists($class, false)
            || interface_exists($class, false)
            || trait_exists($class, false)
            || enum_exists($class, false)
        ) {
            return true;
        }

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            if ($loader->findFile($class) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `(:use ...)` and `(:require ...)` clauses of the ns form, plus the
     * top-level `(use ...)` forms a file opened with `in-ns` imports with.
     *
     * @param list<mixed>                         $forms
     * @param PersistentListInterface<mixed>|null $nsForm
     *
     * @return array{use: array<string, string>, require: array<string, true>}
     */
    private function aliases(array $forms, ?PersistentListInterface $nsForm): array
    {
        $aliases = ['use' => [], 'require' => []];

        if ($nsForm instanceof PersistentListInterface) {
            foreach (['use', 'require'] as $kind) {
                foreach (NsClauseIterator::clauses($nsForm, $kind) as $clause) {
                    $this->addClause($clause, $kind, $aliases);
                }
            }
        }

        foreach ($forms as $form) {
            if (!$form instanceof PersistentListInterface || count($form) < 2) {
                continue;
            }

            $head = $form->get(0);
            if ($head instanceof Symbol && $head->getNamespace() === null && in_array($head->getName(), ['use', 'require'], true)) {
                $this->addClause($form, $head->getName(), $aliases);
            }
        }

        return $aliases;
    }

    /**
     * @param PersistentListInterface<mixed>                                  $clause
     * @param array{use: array<string, string>, require: array<string, true>} $aliases
     */
    private function addClause(PersistentListInterface $clause, string $kind, array &$aliases): void
    {
        $size = count($clause);
        for ($i = 1; $i < $size; ++$i) {
            $item = $clause->get($i);
            if (!$item instanceof Symbol) {
                continue;
            }

            $alias = SymbolAlias::lastSegment($item->getName());
            $next = $i + 2 < $size ? $clause->get($i + 1) : null;
            $aliasSymbol = $i + 2 < $size ? $clause->get($i + 2) : null;
            if ($next instanceof Keyword && $next->getName() === 'as' && $aliasSymbol instanceof Symbol) {
                $alias = $aliasSymbol->getName();
                $i += 2;
            }

            if ($kind === 'use') {
                $aliases['use'][$alias] = ltrim(str_replace('.', '\\', $item->getName()), '\\');
            } else {
                $aliases['require'][$alias] = true;
                $aliases['require'][$item->getName()] = true;
            }
        }
    }

    /**
     * @param list<mixed> $forms
     *
     * @return array<string, true>
     */
    private function declaredClasses(array $forms): array
    {
        $declared = [];
        foreach ($forms as $form) {
            if (!$form instanceof PersistentListInterface || count($form) < 2) {
                continue;
            }

            $head = $form->get(0);
            $name = $form->get(1);
            if ($head instanceof Symbol && $name instanceof Symbol && in_array($head->getName(), self::CLASS_DEFINING_FORMS, true)) {
                $declared[$name->getName()] = true;
            }
        }

        return $declared;
    }
}
