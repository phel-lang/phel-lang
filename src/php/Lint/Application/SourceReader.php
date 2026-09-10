<?php

declare(strict_types=1);

namespace Phel\Lint\Application;

use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Symbol;
use Phel\Lang\TypeInterface;
use Phel\Lint\Domain\SourceRead;
use Phel\Shared\Facade\CompilerFacadeInterface;

use Throwable;

use function count;

/**
 * Lex, parse and read a Phel source string into a list of top-level forms
 * ready for rule inspection. Never throws: best-effort only, so rules can
 * still operate on partial input when later forms are broken.
 *
 * It does report that it stopped early, though. Swallowing the failure
 * silently made `phel lint` call a file that does not even lex clean, and
 * exit 0 (#3292).
 *
 * @internal
 */
final readonly class SourceReader
{
    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
    ) {}

    public function read(string $source, string $uri): SourceRead
    {
        /** @var list<bool|float|int|string|TypeInterface|null> $forms */
        $forms = [];
        $namespace = '';
        $failed = false;

        try {
            $read = $this->compilerFacade->readFormsBestEffort($source, $uri);

            foreach ($read as $form) {
                if ($namespace === '') {
                    $found = $this->maybeNamespace($form);
                    if ($found !== '') {
                        $namespace = $found;
                    }
                }

                $forms[] = $form;
            }

            $failed = $read->getReturn();
        } catch (Throwable) {
            // Best-effort: keep what we managed to read, and say we stopped.
            $failed = true;
        }

        return new SourceRead($namespace, $forms, $failed);
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
