<?php

declare(strict_types=1);

namespace Phel\Mutate\Domain\Mutator;

use InvalidArgumentException;

use function array_keys;
use function array_values;
use function implode;
use function sprintf;

/**
 * Every built-in mutator by id. `--only` selects a subset; an unknown id is
 * an error rather than a silent no-op, so a typo cannot quietly turn a
 * mutation run into a pass.
 *
 * @internal
 */
final class MutatorRegistry
{
    /**
     * @return list<MutatorInterface>
     */
    public static function all(): array
    {
        return [
            new ArithmeticMutator(),
            new ComparisonMutator(),
            new EqualityMutator(),
            new LogicMutator(),
            new ConditionMutator(),
            new BooleanLiteralMutator(),
            new NumberLiteralMutator(),
            new StringLiteralMutator(),
            new SequenceMutator(),
            new ReturnNilMutator(),
            new BodyDropMutator(),
        ];
    }

    /**
     * @param list<string> $ids empty = every mutator
     *
     * @return list<MutatorInterface>
     */
    public static function select(array $ids): array
    {
        $byId = [];
        foreach (self::all() as $mutator) {
            $byId[$mutator->id()] = $mutator;
        }

        if ($ids === []) {
            return array_values($byId);
        }

        $selected = [];
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown mutator "%s". Known mutators: %s.',
                    $id,
                    implode(', ', array_keys($byId)),
                ));
            }

            $selected[] = $byId[$id];
        }

        return $selected;
    }
}
