<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Shared\Facade\CompilerFacadeInterface;

use Throwable;

use function count;

/**
 * Lex, parse and read a Phel source string into a list of top-level forms
 * ready for rule inspection. Never throws: best-effort only, so rules can
 * still operate on partial input when later forms are broken.
 *
 * @internal
 */
final readonly class SourceReader
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    /**
     * @return array{
     *     namespace: string,
     *     forms: list<bool|float|int|string|TypeInterface|null>,
     * }
     */
    public function read(string $source, string $uri): array
    {
        /** @var list<bool|float|int|string|TypeInterface|null> $forms */
        $forms = [];
        $namespace = '';

        try {
            foreach ($this->compilerFacade->readFormsBestEffort($source, $uri) as $form) {
                if ($namespace === '') {
                    $found = $this->maybeNamespace($form);
                    if ($found !== '') {
                        $namespace = $found;
                    }
                }

                $forms[] = $form;
            }
        } catch (Throwable) {
            // Best-effort: return what we managed to read.
        }

        return [
            'namespace' => $namespace,
            'forms' => $forms,
        ];
    }

    private function maybeNamespace(mixed $form): string
    {
        if (!$form instanceof PersistentListInterface || count($form) < 2) {
            return '';
        }

        $head = $form->get(0);
        if (!$head instanceof Symbol || $head->getName() !== Symbol::NAME_NS) {
            return '';
        }

        $name = $form->get(1);
        if (!$name instanceof Symbol) {
            return '';
        }

        return $name->getFullName();
    }
}
