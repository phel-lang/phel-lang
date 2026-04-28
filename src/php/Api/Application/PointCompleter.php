<?php

declare(strict_types=1);

namespace Phel\Api\Application;

use Phel\Api\Domain\PhelFnNormalizerInterface;
use Phel\Api\Domain\PointCompleterInterface;
use Phel\Api\Transfer\Completion;
use Phel\Api\Transfer\Definition;
use Phel\Api\Transfer\ProjectIndex;
use Phel\Compiler\Domain\Parser\Exceptions\AbstractParserException;
use Phel\Compiler\Domain\Parser\ParserNode\NodeInterface;
use Phel\Compiler\Domain\Parser\ParserNode\TriviaNodeInterface;
use Phel\Compiler\Domain\Reader\Exceptions\ReaderException;
use Phel\Lang\Collections\LinkedList\PersistentListInterface;
use Phel\Lang\Collections\Map\PersistentMapInterface;
use Phel\Lang\Collections\Vector\PersistentVectorInterface;
use Phel\Lang\SourceLocation;
use Phel\Lang\Symbol;
use Phel\Shared\Facade\CompilerFacadeInterface;

use Throwable;

use function count;
use function in_array;
use function str_starts_with;

/**
 * Context-aware completion: scans the source parse tree for bindings
 * in scope at the given point, then unions that with
 * project-index definitions and the public phel-core API.
 */
final readonly class PointCompleter implements PointCompleterInterface
{
    private const array BINDING_FORMS = ['let', 'loop', 'for', 'binding', 'if-let', 'when-let'];

    private const array FN_FORMS = ['fn', 'defn', 'defn-', 'defmacro', 'defmacro-'];

    public function __construct(
        private CompilerFacadeInterface $compilerFacade,
        private PhelFnNormalizerInterface $phelFnNormalizer,
    ) {}

    /**
     * @return list<Completion>
     */
    public function completeAtPoint(string $source, int $line, int $col, ProjectIndex $index): array
    {
        $prefix = $this->extractPrefix($source, $line, $col);

        $locals = $this->collectLocalsAtPoint($source, $line, $col);
        $projectDefs = $this->collectProjectDefinitions($index);
        $coreFns = $this->collectCoreFunctions();

        $results = [];
        $seen = [];

        foreach ($locals as $local) {
            if (($prefix === '' || str_starts_with($local, $prefix)) && !isset($seen[$local])) {
                $seen[$local] = true;
                $results[] = new Completion(
                    label: $local,
                    kind: Completion::KIND_LOCAL,
                    detail: 'local binding',
                );
            }
        }

        foreach ($projectDefs as $completion) {
            if ($prefix !== '' && !str_starts_with($completion->label, $prefix)) {
                continue;
            }

            if (!isset($seen[$completion->label])) {
                $seen[$completion->label] = true;
                $results[] = $completion;
            }
        }

        foreach ($coreFns as $completion) {
            if ($prefix !== '' && !str_starts_with($completion->label, $prefix)) {
                continue;
            }

            if (!isset($seen[$completion->label])) {
                $seen[$completion->label] = true;
                $results[] = $completion;
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function collectLocalsAtPoint(string $source, int $line, int $col): array
    {
        $locals = [];

        try {
            $tokenStream = $this->compilerFacade->lexString($source, 'point');
            while (true) {
                try {
                    $parseTree = $this->compilerFacade->parseNext($tokenStream);
                } catch (AbstractParserException) {
                    break;
                }

                if (!$parseTree instanceof NodeInterface) {
                    break;
                }

                if ($parseTree instanceof TriviaNodeInterface) {
                    continue;
                }

                try {
                    $readerResult = $this->compilerFacade->read($parseTree);
                } catch (ReaderException) {
                    continue;
                }

                $this->walk($readerResult->getAst(), $line, $col, [], $locals);
            }
        } catch (Throwable) {
            // Best-effort only
        }

        return array_values(array_unique($locals));
    }

    /**
     * Recursive walker that tracks current-scope bindings and, if the point
     * is inside the given form, appends the scope to $collected.
     *
     * @param list<string> $scope
     * @param list<string> $collected
     */
    private function walk(mixed $form, int $line, int $col, array $scope, array &$collected): void
    {
        if ($form instanceof PersistentListInterface) {
            if (!$this->pointInside($form, $line, $col)) {
                return;
            }

            $newScope = $scope;
            $head = count($form) > 0 ? $form->get(0) : null;
            if ($head instanceof Symbol && in_array($head->getName(), self::BINDING_FORMS, true)) {
                $newScope = $this->extractBindings($form, $scope);
            } elseif ($head instanceof Symbol && in_array($head->getName(), self::FN_FORMS, true)) {
                $newScope = $this->extractFnParams($form, $scope);
            }

            $collected = array_values(array_unique([...$collected, ...$newScope]));

            foreach ($form as $child) {
                $this->walk($child, $line, $col, $newScope, $collected);
            }

            return;
        }

        if ($form instanceof PersistentVectorInterface) {
            foreach ($form as $child) {
                $this->walk($child, $line, $col, $scope, $collected);
            }

            return;
        }

        if ($form instanceof PersistentMapInterface) {
            foreach ($form as $k => $v) {
                $this->walk($k, $line, $col, $scope, $collected);
                $this->walk($v, $line, $col, $scope, $collected);
            }
        }
    }

    /**
     * @param list<string> $scope
     *
     * @return list<string>
     */
    private function extractBindings(PersistentListInterface $form, array $scope): array
    {
        if (count($form) < 2) {
            return $scope;
        }

        $bindingVec = $form->get(1);
        if (!$bindingVec instanceof PersistentVectorInterface) {
            return $scope;
        }

        $result = $scope;
        $size = count($bindingVec);
        for ($i = 0; $i < $size; $i += 2) {
            $binding = $bindingVec->get($i);
            if ($binding instanceof Symbol) {
                $result[] = $binding->getName();
            } elseif ($binding instanceof PersistentVectorInterface) {
                foreach ($binding as $sym) {
                    if ($sym instanceof Symbol) {
                        $result[] = $sym->getName();
                    }
                }
            } elseif ($binding instanceof PersistentMapInterface) {
                foreach ($binding as $v) {
                    if ($v instanceof Symbol) {
                        $result[] = $v->getName();
                    }
                }
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param list<string> $scope
     *
     * @return list<string>
     */
    private function extractFnParams(PersistentListInterface $form, array $scope): array
    {
        $result = $scope;
        $counter = count($form);
        for ($i = 1; $i < $counter; ++$i) {
            $child = $form->get($i);
            if ($child instanceof PersistentVectorInterface) {
                foreach ($child as $param) {
                    if ($param instanceof Symbol && $param->getName() !== '&') {
                        $result[] = $param->getName();
                    }
                }

                break;
            }

            if ($child instanceof PersistentListInterface && count($child) > 0) {
                $head = $child->get(0);
                if ($head instanceof PersistentVectorInterface) {
                    foreach ($head as $param) {
                        if ($param instanceof Symbol && $param->getName() !== '&') {
                            $result[] = $param->getName();
                        }
                    }
                }
            }
        }

        return array_values(array_unique($result));
    }

    private function pointInside(PersistentListInterface|PersistentVectorInterface|PersistentMapInterface $form, int $line, int $col): bool
    {
        $start = $form->getStartLocation();
        $end = $form->getEndLocation();
        if (!$start instanceof SourceLocation || !$end instanceof SourceLocation) {
            return true;
        }

        if ($line < $start->getLine() || $line > $end->getLine()) {
            return false;
        }

        if ($line === $start->getLine() && $col < $start->getColumn()) {
            return false;
        }

        return !($line === $end->getLine() && $col > $end->getColumn());
    }

    /**
     * @return list<Completion>
     */
    private function collectProjectDefinitions(ProjectIndex $index): array
    {
        $result = [];
        foreach ($index->definitions as $def) {
            $result[] = new Completion(
                label: $def->name,
                kind: $def->kind === Definition::KIND_DEFMACRO ? Completion::KIND_MACRO : Completion::KIND_GLOBAL,
                detail: $def->namespace,
                documentation: $def->docstring,
            );
        }

        return $result;
    }

    /**
     * @return list<Completion>
     */
    private function collectCoreFunctions(): array
    {
        $result = [];
        try {
            foreach ($this->phelFnNormalizer->getPhelFunctions(['phel\\core']) as $fn) {
                $result[] = new Completion(
                    label: $fn->name,
                    kind: Completion::KIND_GLOBAL,
                    detail: $fn->namespace,
                    documentation: $fn->description,
                );
            }
        } catch (Throwable) {
            // Core not loaded in this environment: ignore quietly so callers still get locals.
        }

        return $result;
    }

    private function extractPrefix(string $source, int $line, int $col): string
    {
        $lines = preg_split('/\r?\n/', $source) ?: [];
        if (!isset($lines[$line - 1])) {
            return '';
        }

        $slice = substr($lines[$line - 1], 0, max(0, $col - 1));
        if (!preg_match('/([A-Za-z0-9\-_?!*+<>=\/]+)$/', $slice, $matches)) {
            return '';
        }

        return $matches[1];
    }
}
