<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain;

use function count;
use function explode;
use function implode;
use function sprintf;

/**
 * One candidate change to one top-level `defn`, ready to be evaluated in
 * place of the original: the mutated form's full source plus what the
 * report needs to name it.
 *
 * @internal
 */
final readonly class Mutant
{
    /**
     * @param int $line     line of the mutated node
     * @param int $formLine line the whole definition starts on
     */
    public function __construct(
        public string $file,
        public string $namespace,
        public string $definition,
        public int $line,
        public int $column,
        public int $formLine,
        public string $mutator,
        public string $description,
        public string $originalForm,
        public string $mutatedForm,
    ) {}

    /**
     * Stable identity for de-duplication and JSON: file, position, mutator,
     * description.
     */
    public function id(): string
    {
        return $this->file . ':' . $this->line . ':' . $this->column . ' [' . $this->mutator . '] ' . $this->description;
    }

    /**
     * The changed lines of the definition, `-` original and `+` mutated,
     * with their line numbers in the file. A mutation touches one node, so
     * the lines that differ are the run between the first and the last
     * differing line of the two forms.
     */
    public function diff(): string
    {
        $before = explode("\n", $this->originalForm);
        $after = explode("\n", $this->mutatedForm);
        $beforeCount = count($before);
        $afterCount = count($after);

        $prefix = 0;
        while ($prefix < $beforeCount && $prefix < $afterCount && $before[$prefix] === $after[$prefix]) {
            ++$prefix;
        }

        $suffix = 0;
        while ($suffix < $beforeCount - $prefix
            && $suffix < $afterCount - $prefix
            && $before[$beforeCount - 1 - $suffix] === $after[$afterCount - 1 - $suffix]
        ) {
            ++$suffix;
        }

        $lines = [];
        for ($i = $prefix; $i < $beforeCount - $suffix; ++$i) {
            $lines[] = sprintf('-%d: %s', $this->formLine + $i, $before[$i]);
        }

        for ($i = $prefix; $i < $afterCount - $suffix; ++$i) {
            $lines[] = sprintf('+%d: %s', $this->formLine + $i, $after[$i]);
        }

        return implode("\n", $lines);
    }
}
