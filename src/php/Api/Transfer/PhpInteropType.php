<?php

declare(strict_types=1);

namespace Phel\Api\Transfer;

use function count;
use function in_array;

/**
 * @internal
 */
final readonly class PhpInteropType
{
    private const array BUILTIN_TYPES = [
        'array', 'bool', 'callable', 'false', 'float', 'int', 'iterable',
        'mixed', 'never', 'null', 'object', 'string', 'true', 'void',
    ];

    private const array SCALAR_TYPES = ['array', 'bool', 'false', 'float', 'int', 'null', 'string', 'true', 'void'];

    /**
     * @param list<list<string>> $alternatives
     */
    private function __construct(public array $alternatives) {}

    public static function fromName(string $name): self
    {
        if ($name === '') {
            return new self([]);
        }

        $alternatives = [];
        foreach (explode('|', $name) as $alternative) {
            $members = self::normalizeIntersection(explode('&', trim($alternative, '()')));
            if ($members === null) {
                continue;
            }

            sort($members);
            $alternatives[implode('&', $members)] = $members;
        }

        ksort($alternatives);

        return new self(array_values($alternatives));
    }

    public function union(self $other): self
    {
        return self::fromName(implode('|', array_filter([$this->name(), $other->name()])));
    }

    public function intersect(self $other): self
    {
        $alternatives = [];
        foreach ($this->alternatives as $left) {
            foreach ($other->alternatives as $right) {
                $alternatives[] = implode('&', [...$left, ...$right]);
            }
        }

        return self::fromName(implode('|', $alternatives));
    }

    /**
     * @return list<list<string>>
     */
    public function objectAlternatives(): array
    {
        return array_values(array_filter(
            $this->alternatives,
            static fn(array $members): bool => array_intersect($members, self::SCALAR_TYPES) === [],
        ));
    }

    public function name(): string
    {
        return implode('|', array_map(
            fn(array $members): string => count($members) > 1 && count($this->alternatives) > 1
                ? '(' . implode('&', $members) . ')'
                : implode('&', $members),
            $this->alternatives,
        ));
    }

    /**
     * @param list<string> $members
     *
     * @return list<string>|null
     */
    private static function normalizeIntersection(array $members): ?array
    {
        $members = array_values(array_unique($members));
        if (count($members) > 1) {
            $members = array_values(array_diff($members, ['mixed']));
        }

        if (in_array('never', $members, true)) {
            return null;
        }

        if (count($members) > 1 && in_array('null', $members, true)) {
            return null;
        }

        $scalars = array_values(array_intersect($members, self::SCALAR_TYPES));
        $classes = array_diff($members, self::BUILTIN_TYPES);
        if ($scalars !== [] && ($classes !== [] || in_array('object', $members, true))) {
            return null;
        }

        if (count($scalars) > 1) {
            if (count($scalars) !== 2 || !in_array('bool', $scalars, true) || (!in_array('true', $scalars, true) && !in_array('false', $scalars, true))) {
                return null;
            }

            $members = array_values(array_diff($members, ['bool']));
        }

        if ($scalars !== [] && in_array('iterable', $members, true) && !in_array('array', $scalars, true)) {
            return null;
        }

        if (array_intersect($scalars, ['bool', 'false', 'float', 'int', 'null', 'true', 'void']) !== [] && in_array('callable', $members, true)) {
            return null;
        }

        return $members;
    }
}
