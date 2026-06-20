<?php

declare(strict_types=1);

namespace Phel\Shared\Exceptions\Hint;

use Throwable;

use function preg_match;
use function sprintf;

final class NotCallableHint implements ExceptionHintInterface
{
    private const array PHEL_TYPE_LABELS = [
        '/^Phel\\\\Lang\\\\Collections\\\\LazySeq\\\\/' => 'sequence',
        '/^Phel\\\\Lang\\\\Collections\\\\Vector\\\\/' => 'vector',
        '/^Phel\\\\Lang\\\\Collections\\\\Map\\\\/' => 'map',
        '/^Phel\\\\Lang\\\\Collections\\\\SortedMap\\\\/' => 'sorted map',
        '/^Phel\\\\Lang\\\\Collections\\\\HashSet\\\\/' => 'set',
        '/^Phel\\\\Lang\\\\Collections\\\\SortedSet\\\\/' => 'sorted set',
        '/^Phel\\\\Lang\\\\Collections\\\\LinkedList\\\\/' => 'list',
        '/^Phel\\\\Lang\\\\Collections\\\\Queue\\\\/' => 'queue',
        '/^Phel\\\\Lang\\\\Collections\\\\Struct\\\\/' => 'struct',
        '/^Phel\\\\Lang\\\\Keyword$/' => 'keyword',
        '/^Phel\\\\Lang\\\\Symbol$/' => 'symbol',
    ];

    public function appliesTo(Throwable $e): bool
    {
        return $this->extractType($e->getMessage()) !== null;
    }

    public function hint(Throwable $e): string
    {
        $type = $this->extractType($e->getMessage()) ?? 'value';
        $label = $this->phelLabel($type) ?? $type;

        return sprintf(
            "value of type '%s' is not a function. Drop the parens, or use an accessor like (first), (nth), or (get).",
            $label,
        );
    }

    private function extractType(string $message): ?string
    {
        if (preg_match('/Object of type ([\\w\\\\]+) is not callable/', $message, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function phelLabel(string $fqcn): ?string
    {
        foreach (self::PHEL_TYPE_LABELS as $pattern => $label) {
            if (preg_match($pattern, $fqcn) === 1) {
                return $label;
            }
        }

        return null;
    }
}
